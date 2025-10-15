# ZenaManage Deduplication Troubleshooting Guide

## 🚨 Common Issues và Solutions

### Git Hooks Issues

#### Issue: Git hooks not executing
**Symptoms**: Hooks don't run when committing or pushing
**Solutions**:
```bash
# Check if hooks are installed
ls -la .git/hooks/

# Reinstall hooks
./scripts/setup-hooks.sh

# Check hook permissions
chmod +x .git/hooks/pre-commit
chmod +x .git/hooks/commit-msg
chmod +x .git/hooks/post-commit
chmod +x .git/hooks/pre-push

# Verify git configuration
git config --list | grep hooks
```

#### Issue: Pre-commit hook blocking commits
**Symptoms**: Cannot commit due to duplicate detection warnings
**Solutions**:
```bash
# Bypass hook temporarily (not recommended)
git commit --no-verify

# Fix duplicate issues first
./scripts/check-duplicates.sh

# Remove duplicate code
# Then commit normally
```

#### Issue: Commit-msg hook rejecting messages
**Symptoms**: Commit messages rejected due to format issues
**Solutions**:
```bash
# Use proper commit message format
git commit -m "feat: add new feature"

# Or bypass temporarily
git commit --no-verify -m "your message"
```

### CI/CD Pipeline Issues

#### Issue: GitHub Actions failing
**Symptoms**: Workflow fails with errors
**Solutions**:
```bash
# Check workflow syntax
# Validate YAML in .github/workflows/

# Check dependencies
npm ci
composer install

# Check tool versions
node --version
php --version

# Review workflow logs
# Check GitHub Actions tab for detailed logs
```

#### Issue: Duplicate detection tools not found
**Symptoms**: jscpd or phpcpd not found
**Solutions**:
```bash
# Install jscpd
npm install -g jscpd

# Install phpcpd
composer global require sebastian/phpcpd

# Check PATH
echo $PATH
which jscpd
which phpcpd
```

#### Issue: ESLint configuration errors
**Symptoms**: ESLint fails with configuration errors
**Solutions**:
```bash
# Check ESLint config
cat .eslintrc.sonarjs.js

# Install missing plugins
npm install eslint-plugin-sonarjs

# Run ESLint manually
npx eslint resources/js --ext .js,.ts,.jsx,.tsx
```

### Local Detection Script Issues

#### Issue: Script not executable
**Symptoms**: Permission denied when running scripts
**Solutions**:
```bash
# Make scripts executable
chmod +x scripts/check-duplicates.sh
chmod +x scripts/setup-hooks.sh
chmod +x scripts/install-hooks.sh
chmod +x scripts/uninstall-hooks.sh

# Check permissions
ls -la scripts/
```

#### Issue: Script not finding files
**Symptoms**: Script reports no files found
**Solutions**:
```bash
# Check file paths
find . -name "*.php" -not -path "./vendor/*"
find . -name "*.js" -not -path "./node_modules/*"

# Check current directory
pwd

# Run with debug output
bash -x scripts/check-duplicates.sh
```

#### Issue: Script running slowly
**Symptoms**: Script takes too long to execute
**Solutions**:
```bash
# Optimize file searches
# Use more specific patterns
# Exclude unnecessary directories
# Use parallel execution where possible
```

### Performance Issues

#### Issue: Git hooks slowing down git operations
**Symptoms**: Git commands take longer than expected
**Solutions**:
```bash
# Optimize hook execution
# Add timeouts to hooks
# Use parallel execution
# Cache results where possible

# Temporarily disable hooks
mv .git/hooks/pre-commit .git/hooks/pre-commit.disabled
```

#### Issue: CI/CD pipeline taking too long
**Symptoms**: GitHub Actions take hours to complete
**Solutions**:
```bash
# Optimize job dependencies
# Use parallel execution
# Cache dependencies
# Optimize tool execution
# Reduce file scanning scope
```

#### Issue: Local detection script consuming too much memory
**Symptoms**: Script uses excessive memory
**Solutions**:
```bash
# Optimize file processing
# Process files in batches
# Use streaming where possible
# Reduce memory footprint
```

### Configuration Issues

#### Issue: Duplicate detection thresholds too strict
**Symptoms**: Too many false positives
**Solutions**:
```bash
# Adjust thresholds in workflows
# Increase min-lines and min-tokens
# Update file pattern exclusions
# Review and refine rules
```

#### Issue: Duplicate detection thresholds too loose
**Symptoms**: Missing actual duplicates
**Solutions**:
```bash
# Decrease thresholds
# Add more specific patterns
# Review detection rules
# Add custom rules
```

#### Issue: Tool configuration conflicts
**Symptoms**: Tools conflict with each other
**Solutions**:
```bash
# Check tool versions
# Update conflicting tools
# Review configuration files
# Use consistent settings
```

### Data Issues

#### Issue: Mock data still present
**Symptoms**: Mock data found in codebase
**Solutions**:
```bash
# Search for mock data
grep -r "mock\|dummy\|fake\|sample" --include="*.php" --include="*.js" .

# Replace with real data
# Update services to use real data
# Remove mock data files
# Update tests
```

#### Issue: Real data services not working
**Symptoms**: Real data services return errors
**Solutions**:
```bash
# Check database connection
php artisan migrate:status

# Check service configuration
# Review service implementations
# Check database queries
# Verify data models
```

### Integration Issues

#### Issue: Unified components not working
**Symptoms**: Unified components fail to render
**Solutions**:
```bash
# Check component imports
# Verify component props
# Check React/Vue configuration
# Review component implementations
```

#### Issue: API endpoints not responding
**Symptoms**: API calls fail
**Solutions**:
```bash
# Check API routes
php artisan route:list

# Check middleware
# Verify controller implementations
# Check database connections
# Review API documentation
```

## 🔧 Debugging Techniques

### Enable Debug Mode
```bash
# Enable debug output
export DEBUG=1
bash -x scripts/check-duplicates.sh

# Enable verbose logging
export VERBOSE=1
./scripts/check-duplicates.sh
```

### Check System Requirements
```bash
# Check Node.js version
node --version

# Check PHP version
php --version

# Check Composer version
composer --version

# Check Git version
git --version
```

### Validate Configuration
```bash
# Check ESLint config
npx eslint --print-config resources/js/app.js

# Check PHP CS Fixer config
php-cs-fixer --dry-run --diff app/

# Check PHPStan config
phpstan analyse app/ --level=5
```

### Test Individual Components
```bash
# Test jscpd
npx jscpd --version
npx jscpd --min-lines 10 --min-tokens 50 resources/js/

# Test phpcpd
phpcpd --version
phpcpd --min-lines 10 --min-tokens 50 app/

# Test ESLint
npx eslint --version
npx eslint resources/js/app.js
```

## 📞 Getting Help

### Internal Resources
1. **Documentation**: Check project documentation first
2. **Team Chat**: Ask team members for help
3. **Code Reviews**: Request code reviews
4. **Issue Tracking**: Create issues for problems

### External Resources
1. **Tool Documentation**: Check official tool documentation
2. **Stack Overflow**: Search for similar issues
3. **GitHub Issues**: Check tool GitHub repositories
4. **Community Forums**: Ask in community forums

### Escalation Path
1. **Technical Issues**: Escalate to technical lead
2. **Process Issues**: Escalate to process owner
3. **Tool Issues**: Escalate to tool maintainer
4. **Architecture Issues**: Escalate to architect

## 📋 Prevention Checklist

### Before Making Changes
- [ ] Run local duplicate detection
- [ ] Check for existing similar functionality
- [ ] Review unified component usage
- [ ] Follow established patterns
- [ ] Update documentation if needed

### During Development
- [ ] Use existing unified components
- [ ] Follow consistent naming conventions
- [ ] Write tests for new functionality
- [ ] Document public APIs
- [ ] Review code for duplicates

### After Making Changes
- [ ] Run duplicate detection
- [ ] Check CI/CD results
- [ ] Update documentation
- [ ] Share learnings với team
- [ ] Monitor for new duplicates

## 🎯 Best Practices

### Development Practices
1. **Use Existing Components**: Prefer existing unified components
2. **Follow Patterns**: Use established patterns và conventions
3. **Check for Duplicates**: Always check for existing similar functionality
4. **Update Documentation**: Keep documentation current
5. **Test Changes**: Test all changes thoroughly

### Troubleshooting Practices
1. **Check Documentation**: Always check documentation first
2. **Reproduce Issues**: Reproduce issues in isolated environment
3. **Gather Information**: Collect relevant information about issues
4. **Test Solutions**: Test solutions before implementing
5. **Document Solutions**: Document solutions for future reference

### Maintenance Practices
1. **Regular Monitoring**: Monitor duplicate detection regularly
2. **Proactive Maintenance**: Address issues before they become problems
3. **Continuous Improvement**: Continuously improve processes và tools
4. **Knowledge Sharing**: Share learnings với team
5. **Documentation Updates**: Keep documentation current

This troubleshooting guide provides comprehensive solutions for common issues encountered during deduplication maintenance. Regular use of this guide will help maintain the effectiveness of the deduplication system.
