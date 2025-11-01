# ZenaManage Deduplication Maintenance Guide

## 📋 Overview

This guide provides comprehensive instructions for maintaining the deduplication system implemented in the ZenaManage project. It covers ongoing duplicate detection, prevention strategies, và troubleshooting common issues.

## 🔧 Maintenance Tools

### Local Duplicate Detection
```bash
# Run local duplicate detection
./scripts/check-duplicates.sh

# Install git hooks
./scripts/setup-hooks.sh

# Uninstall git hooks
./scripts/uninstall-hooks.sh
```

### Git Hooks
- **Pre-commit**: Checks staged files for duplicate patterns
- **Commit-msg**: Validates commit messages for duplicate-related keywords
- **Post-commit**: Provides summary after commit
- **Pre-push**: Checks commits before pushing

### CI/CD Pipeline
- **GitHub Actions**: Automated duplicate detection on push/PR
- **Artifacts**: Reports uploaded as artifacts for review
- **PR Comments**: Automated comments với duplicate detection results

## 📊 Monitoring Duplicate Detection

### Daily Monitoring
1. **Check CI/CD Results**: Review GitHub Actions for duplicate detection results
2. **Monitor PR Comments**: Check automated duplicate detection comments
3. **Review Local Detection**: Run local detection script regularly
4. **Check Git Hooks**: Ensure hooks are working properly

### Weekly Monitoring
1. **Analyze Trends**: Review duplicate detection trends over time
2. **Update Thresholds**: Adjust thresholds based on results
3. **Review Documentation**: Keep documentation current
4. **Team Training**: Ensure team understands new patterns

### Monthly Monitoring
1. **Architecture Review**: Review architecture for drift
2. **Tool Enhancement**: Enhance duplicate detection tools
3. **Process Improvement**: Improve development processes
4. **Knowledge Sharing**: Share learnings với team

## 🚨 Troubleshooting

### Common Issues

#### Git Hooks Not Working
```bash
# Check if hooks are installed
ls -la .git/hooks/

# Reinstall hooks
./scripts/setup-hooks.sh

# Check hook permissions
chmod +x .git/hooks/pre-commit
```

#### CI/CD Pipeline Failing
1. **Check Workflow Syntax**: Validate YAML syntax
2. **Review Dependencies**: Check tool dependencies
3. **Update Thresholds**: Adjust duplicate detection thresholds
4. **Check Artifacts**: Review uploaded artifacts

#### Local Detection Script Issues
```bash
# Check script permissions
chmod +x scripts/check-duplicates.sh

# Run with debug output
bash -x scripts/check-duplicates.sh

# Check file paths
find . -name "*.php" -not -path "./vendor/*"
```

### Performance Issues

#### Slow Git Hooks
```bash
# Optimize hook execution
# Add timeout to hooks
# Use parallel execution where possible
```

#### CI/CD Pipeline Slow
```bash
# Optimize job dependencies
# Use parallel execution
# Cache dependencies
# Optimize tool execution
```

## 🔍 Duplicate Detection Configuration

### Thresholds
```yaml
# jscpd configuration
min-lines: 10
min-tokens: 50
threshold: 5

# phpcpd configuration
min-lines: 10
min-tokens: 50
```

### File Patterns
```bash
# Header files
find resources/views -name "*header*" -type f

# Layout files
find resources/views/layouts -name "app*.blade.php" -type f

# Dashboard files
find resources/views/app -name "*dashboard*" -type f

# Project files
find resources/views/app -name "*project*" -type f

# Middleware files
find app/Http/Middleware -name "*.php" -type f

# Controller files
find app/Http/Controllers -name "*.php" -type f
```

### Exclusions
```bash
# Exclude vendor files
--exclude "vendor/*"

# Exclude legacy files
--exclude "_legacy/*"

# Exclude node_modules
--exclude "node_modules/*"
```

## 📝 Code Review Guidelines

### Pre-commit Checklist
- [ ] Run local duplicate detection
- [ ] Check for existing similar functionality
- [ ] Use existing unified components
- [ ] Follow established patterns
- [ ] Update documentation if needed

### PR Review Checklist
- [ ] Review duplicate detection results
- [ ] Check for new duplicate patterns
- [ ] Verify unified component usage
- [ ] Ensure consistent patterns
- [ ] Update documentation if needed

### Post-commit Checklist
- [ ] Review commit summary
- [ ] Check CI/CD results
- [ ] Monitor for new duplicates
- [ ] Update team on changes
- [ ] Document lessons learned

## 🛠️ Tool Maintenance

### Updating Tools
```bash
# Update jscpd
npm update jscpd

# Update phpcpd
composer global update sebastian/phpcpd

# Update ESLint
npm update eslint eslint-plugin-sonarjs
```

### Tool Configuration
```json
// .eslintrc.sonarjs.js
{
  "extends": ["plugin:sonarjs/recommended"],
  "rules": {
    "sonarjs/no-duplicate-string": "error",
    "sonarjs/no-identical-functions": "error"
  }
}
```

### Custom Rules
```javascript
// Custom duplicate detection rules
const customRules = {
  'no-duplicate-imports': 'error',
  'no-duplicate-keys': 'error',
  'no-duplicate-case': 'error'
};
```

## 📚 Documentation Maintenance

### Updating Documentation
1. **Keep Current**: Update documentation với code changes
2. **Add Examples**: Include practical examples
3. **Update Screenshots**: Keep screenshots current
4. **Review Links**: Ensure all links work
5. **Test Instructions**: Verify all instructions work

### Documentation Structure
```
docs/
├── deduplication/
│   ├── FINAL_REPORT.md
│   ├── MAINTENANCE_GUIDE.md
│   ├── TROUBLESHOOTING.md
│   └── BEST_PRACTICES.md
├── architecture/
│   ├── UNIFIED_COMPONENTS.md
│   ├── CI_CD_PIPELINE.md
│   └── GIT_HOOKS.md
└── development/
    ├── SETUP_GUIDE.md
    ├── CONTRIBUTING.md
    └── CODE_STYLE.md
```

## 🎯 Best Practices

### Development Practices
1. **Use Existing Components**: Prefer existing unified components
2. **Follow Patterns**: Use established patterns và conventions
3. **Check for Duplicates**: Always check for existing similar functionality
4. **Update Documentation**: Keep documentation current
5. **Test Changes**: Test all changes thoroughly

### Code Organization
1. **Single Responsibility**: Each component has single responsibility
2. **Consistent Naming**: Use consistent naming conventions
3. **Clear Structure**: Organize code in clear structure
4. **Documentation**: Document all public APIs
5. **Testing**: Write tests for all functionality

### Team Collaboration
1. **Code Reviews**: Always review code for duplicates
2. **Knowledge Sharing**: Share learnings với team
3. **Documentation**: Keep documentation current
4. **Training**: Train team on new patterns
5. **Feedback**: Provide feedback on duplicate detection

## 🚀 Continuous Improvement

### Regular Reviews
1. **Weekly**: Review duplicate detection results
2. **Monthly**: Review architecture for drift
3. **Quarterly**: Review tools và processes
4. **Annually**: Review overall strategy

### Metrics Tracking
1. **Duplicate Detection**: Track duplicate detection results
2. **Code Quality**: Monitor code quality metrics
3. **Developer Experience**: Track developer satisfaction
4. **Performance**: Monitor performance metrics

### Process Improvement
1. **Feedback Collection**: Collect feedback from team
2. **Tool Enhancement**: Enhance duplicate detection tools
3. **Process Optimization**: Optimize development processes
4. **Knowledge Sharing**: Share learnings với other projects

## 📞 Support

### Getting Help
1. **Documentation**: Check documentation first
2. **Team Chat**: Ask team for help
3. **Issue Tracking**: Create issues for problems
4. **Code Reviews**: Request code reviews
5. **Training**: Request training sessions

### Escalation
1. **Technical Issues**: Escalate to technical lead
2. **Process Issues**: Escalate to process owner
3. **Tool Issues**: Escalate to tool maintainer
4. **Architecture Issues**: Escalate to architect

## 📋 Checklist

### Daily Tasks
- [ ] Check CI/CD results
- [ ] Monitor PR comments
- [ ] Run local detection if needed
- [ ] Review git hooks status

### Weekly Tasks
- [ ] Analyze duplicate detection trends
- [ ] Update thresholds if needed
- [ ] Review documentation
- [ ] Train team members

### Monthly Tasks
- [ ] Review architecture
- [ ] Enhance tools
- [ ] Improve processes
- [ ] Share learnings

### Quarterly Tasks
- [ ] Review overall strategy
- [ ] Update documentation
- [ ] Train new team members
- [ ] Plan improvements

This maintenance guide ensures the deduplication system remains effective và continues to provide value to the development team. Regular maintenance và monitoring are essential for long-term success.
