import { Project, SyntaxKind } from 'ts-morph';
import path from 'path';

/**
 * Codemod outline:
 * 1. find LegacyHeader import and replace with HeaderShell.
 * 2. Map props (logo, menuItems, etc.) to new API.
 * 3. Insert PrimaryNav + slots when missing.
 */
const project = new Project({
  tsConfigFilePath: path.resolve('tsconfig.json'),
});

const files = project.getSourceFiles('src/**/*.tsx');

files.forEach(file => {
  let didChange = false;

  // Replace import declarations
  file.getImportDeclarations().forEach(decl => {
    if (decl.getModuleSpecifierValue().includes('LegacyHeader')) {
      decl.setModuleSpecifier("@/components/ui/header/HeaderShell");
      decl.getNamedImports().forEach(named => {
        if (named.getName() === 'LegacyHeader') {
          named.renameAlias('HeaderShell');
          named.replaceWithText('HeaderShell');
        }
      });
      didChange = true;
    }
  });

  // Replace JSX tags
  file.forEachDescendant(node => {
    if (node.getKind() === SyntaxKind.JsxOpeningElement || node.getKind() === SyntaxKind.JsxSelfClosingElement) {
      const jsx = node.asKind(SyntaxKind.JsxOpeningElement) || node.asKind(SyntaxKind.JsxSelfClosingElement);
      if (jsx && jsx.getTagNameNode().getText() === 'LegacyHeader') {
        jsx.getTagNameNode().replaceWithText('HeaderShell');
        didChange = true;
      }
    }
    if (node.getKind() === SyntaxKind.JsxClosingElement) {
      const closing = node.asKind(SyntaxKind.JsxClosingElement);
      if (closing && closing.getTagNameNode().getText() === 'LegacyHeader') {
        closing.getTagNameNode().replaceWithText('HeaderShell');
        didChange = true;
      }
    }
  });

  if (didChange) {
    file.fixUnusedIdentifiers();
  }
});

project.save().then(() => {
  console.log('HeaderShell codemod finished. Review diff for prop alignment.');
});
