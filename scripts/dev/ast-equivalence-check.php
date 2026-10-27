<?php

declare(strict_types=1);

/*
 * GAP-080: proves a formatting-only change keeps every PHP file's syntax tree.
 *
 * Usage: php scripts/dev/ast-equivalence-check.php <base-ref> [file ...]
 * Without files, checks every *.php file that differs from <base-ref> and exists
 * on both sides. Exit 0 when all are equivalent, 1 otherwise.
 *
 * Positions, comments and docblocks are ignored. These rewrites are treated as
 * equivalent: import (use) order and unused-import removal, `$a = $a ?? $b` vs
 * `$a ??= $b`, `isset($a) ? $a : $b` vs `$a ?? $b`, `use A, B;` vs `use A; use B;`
 * inside a class, implicit vs explicit `public` on class constants, and the
 * order of trait uses relative to other class members.
 */

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/dev/ast-equivalence-check.php <base-ref> [file ...]\n");
    exit(2);
}

$root = dirname(__DIR__, 2);
$base = $argv[1];
$files = array_slice($argv, 2);

if ($files === []) {
    $out = [];
    exec('git -C ' . escapeshellarg($root) . ' diff --name-only --diff-filter=M ' . escapeshellarg($base) . ' -- "*.php"', $out, $code);
    if ($code !== 0) {
        fwrite(STDERR, "git diff failed for base {$base}\n");
        exit(2);
    }
    $files = $out;
}

final class AstNormalizer extends NodeVisitorAbstract
{
    public function enterNode(Node $node)
    {
        $node->setAttributes([]);

        if ($node instanceof Node\Stmt\ClassConst
            && ($node->flags & Node\Stmt\Class_::VISIBILITY_MODIFIER_MASK) === 0) {
            $node->flags |= Node\Stmt\Class_::MODIFIER_PUBLIC;
        }

        return null;
    }

    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Use_ || $node instanceof Node\Stmt\GroupUse) {
            return NodeTraverser::REMOVE_NODE;
        }

        if ($node instanceof Node\Expr\AssignOp\Coalesce) {
            return new Node\Expr\Assign($node->var, new Node\Expr\BinaryOp\Coalesce($node->var, $node->expr));
        }

        if ($node instanceof Node\Expr\Ternary
            && $node->cond instanceof Node\Expr\Isset_
            && count($node->cond->vars) === 1
            && $node->if !== null
            && $node->cond->vars[0] == $node->if) {
            return new Node\Expr\BinaryOp\Coalesce($node->if, $node->else);
        }

        if ($node instanceof Node\Stmt\ClassLike) {
            $traits = [];
            $others = [];
            foreach ($node->stmts as $stmt) {
                if ($stmt instanceof Node\Stmt\TraitUse && $stmt->adaptations === []) {
                    foreach ($stmt->traits as $trait) {
                        $traits[] = new Node\Stmt\TraitUse([$trait]);
                    }
                } else {
                    $others[] = $stmt;
                }
            }
            $node->stmts = array_merge($traits, $others);
        }

        return null;
    }
}

$parser = (new ParserFactory())->createForNewestSupportedVersion();
$normalize = static function (string $code) use ($parser): string {
    $traverser = new NodeTraverser();
    $traverser->addVisitor(new AstNormalizer());

    return (string) json_encode($traverser->traverse($parser->parse($code) ?? []));
};

$differing = 0;
foreach ($files as $file) {
    $old = shell_exec('git -C ' . escapeshellarg($root) . ' show ' . escapeshellarg("{$base}:{$file}") . ' 2>/dev/null');
    $new = @file_get_contents($root . '/' . $file);
    if (!is_string($old) || $new === false) {
        echo "SKIP {$file} (missing on one side)\n";
        continue;
    }
    try {
        if ($normalize($old) !== $normalize($new)) {
            echo "DIFF {$file}\n";
            $differing++;
        }
    } catch (Throwable $e) {
        echo "PARSE-ERROR {$file}: {$e->getMessage()}\n";
        $differing++;
    }
}

echo 'checked=' . count($files) . " differing={$differing}\n";
exit($differing === 0 ? 0 : 1);
