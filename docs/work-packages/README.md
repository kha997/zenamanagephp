# Test Organization Work Packages

This directory contains work packages for organizing test suites by domain.

## Available Packages

1. **auth-domain.md** - Authentication domain (Seed: 12345)
2. **projects-domain.md** - Projects domain (Seed: 23456)
3. **tasks-domain.md** - Tasks domain (Seed: 34567)
4. **documents-domain.md** - Documents domain (Seed: 45678)
5. **users-domain.md** - Users domain (Seed: 56789)
6. **dashboard-domain.md** - Dashboard domain (Seed: 67890)

## How to Use

1. **Pick a package** from the list above
2. **Read the package file** completely
3. **Update progress tracker** in `docs/TEST_ORGANIZATION_PROGRESS.md`
4. **Create a branch:** `git checkout -b test-org/[domain]-domain`
5. **Follow the tasks** in the package file
6. **Verify** using provided commands
7. **Update progress** when complete

## Creating New Packages

Use the script to create new work packages:

```bash
./scripts/create-work-package.sh <domain> <seed>
```

Example:
```bash
./scripts/create-work-package.sh core 78901
```

## Package Status

Check `docs/TEST_ORGANIZATION_PROGRESS.md` for current status of all packages.

