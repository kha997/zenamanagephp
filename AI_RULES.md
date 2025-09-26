# ZENAMANAGE AI ASSISTANT RULES
## MANDATORY GUIDELINES FOR AI INTERACTIONS

### 🎯 **CORE PRINCIPLES**

#### **1. ALWAYS FOLLOW PROJECT_RULES.md**
- Every action must comply with the 13 non-negotiable principles
- Architecture decisions must align with the defined scope
- Error handling must follow the standard envelope format
- Multi-tenant isolation is mandatory for all operations

#### **2. CODE QUALITY STANDARDS**
- **No TODOs or debug code** in production
- **Proper error handling** with structured error envelopes
- **Tenant scoping** on every database query
- **Logging** with X-Request-Id correlation
- **Security first** - validate all inputs, sanitize outputs

#### **3. TESTING REQUIREMENTS**
- **Unit tests** for all services and validators
- **Integration tests** for controllers with DB + auth
- **E2E tests** for critical user paths
- **Isolation tests** to prove tenant separation
- **Performance tests** to meet latency budgets

#### **4. UX/UI DESIGN REQUIREMENTS**
- **Universal Page Frame**: Follow the standard structure (Header → Global Nav → Page Nav → KPI Strip → Alert Bar → Main Content → Activity)
- **Mobile-first design**: Responsive layouts with FAB, hamburger menus, card layouts
- **Accessibility compliance**: WCAG 2.1 AA standards with keyboard navigation
- **Performance budgets**: Page p95 < 500ms, API p95 < 300ms
- **Error/Empty states**: Include friendly CTAs and suggested actions
- **User customization**: Persist preferences for views, density, theme, KPI selection
- **Smart tools**: Implement intelligent search, smart filters, one-tap focus presets

#### **5. DOCUMENTATION OBLIGATIONS**
- **OpenAPI/Swagger** specs for all API endpoints
- **Architecture decisions** documented
- **Error codes** documented with examples
- **Multi-tenant patterns** explained
- **Performance benchmarks** recorded

### 🚫 **ABSOLUTE PROHIBITIONS**

#### **NEVER DO:**
- Create routes without proper middleware
- Write code without tenant isolation
- Skip error handling or use generic errors
- Commit code without tests
- Use hardcoded values or magic numbers
- Create duplicate functionality
- Bypass security validations
- Ignore performance budgets
- Leave debug code in production
- Create orphaned data

#### **NEVER ACCEPT:**
- PRs without proper tests
- Code that violates architecture principles
- Changes without documentation updates
- Performance regressions
- Security vulnerabilities
- Data isolation violations

### ✅ **MANDATORY ACTIONS**

#### **ALWAYS DO:**
- Validate all inputs against schemas
- Include proper error handling with error.id
- Add tenant_id to all queries
- Write tests for new functionality
- Update documentation for changes
- Follow naming conventions strictly
- Include logging with correlation IDs
- Check performance impact
- Verify security implications
- Ensure multi-tenant isolation

#### **REQUIRED CHECKS:**
- Architecture compliance
- Security validation
- Performance impact
- Test coverage
- Documentation updates
- Error handling completeness
- Tenant isolation verification
- Code quality standards

### 🔍 **REVIEW PROCESS**

#### **Before Any Code Change:**
1. **Architecture Review**: Does this follow the defined patterns?
2. **Security Check**: Are all inputs validated and outputs sanitized?
3. **Performance Impact**: Will this meet latency budgets?
4. **Multi-tenant Safety**: Is tenant isolation maintained?
5. **Error Handling**: Are all error cases covered?

#### **During Development:**
1. **Continuous Testing**: Run tests after each change
2. **Code Quality**: Follow naming conventions and patterns
3. **Documentation**: Update docs as you code
4. **Logging**: Add appropriate logging with correlation IDs
5. **Security**: Validate inputs and sanitize outputs

#### **Before Completion:**
1. **Full Test Suite**: All tests must pass
2. **Performance Validation**: Meet latency budgets
3. **Security Review**: No vulnerabilities introduced
4. **Documentation Complete**: All changes documented
5. **Architecture Compliance**: Follows all principles

### 📊 **SUCCESS METRICS**

#### **Code Quality:**
- 100% test coverage for new code
- 0 security vulnerabilities
- < 500ms p95 latency for pages
- < 300ms p95 latency for APIs
- 0 tenant isolation violations

#### **Documentation:**
- 100% API endpoints documented
- All error codes with examples
- Architecture decisions recorded
- Performance benchmarks documented
- Multi-tenant patterns explained

#### **Compliance:**
- 100% adherence to naming conventions
- All routes properly scoped
- No debug code in production
- Complete error handling
- Proper logging implementation

### 🚨 **ESCALATION PROCEDURES**

#### **CRITICAL Issues:**
- Security vulnerabilities → Immediate fix required
- Data isolation violations → Block all changes
- Performance regressions → Rollback if deployed
- Architecture violations → Require redesign

#### **HIGH Priority:**
- Missing tests → Block merge
- Documentation gaps → Block merge
- Error handling gaps → Block merge
- Performance issues → Fix before merge

#### **MEDIUM Priority:**
- Naming violations → Fix in same PR
- Code style issues → Fix in same PR
- Minor optimizations → Fix in next PR

### 🔄 **CONTINUOUS IMPROVEMENT**

#### **Weekly Reviews:**
- Performance metrics analysis
- Error pattern identification
- Security vulnerability assessment
- Test coverage evaluation

#### **Monthly Reviews:**
- Architecture decision validation
- Documentation completeness
- Process improvement opportunities
- Technology stack evaluation

#### **Quarterly Reviews:**
- Complete rule set review
- Architecture modernization
- Performance optimization
- Security enhancement

---

## 🎯 **AI ASSISTANT COMMITMENT**

I commit to:
- **Always follow** the PROJECT_RULES.md principles
- **Never compromise** on security or performance
- **Always validate** multi-tenant isolation
- **Always include** proper error handling
- **Always write** comprehensive tests
- **Always update** documentation
- **Always maintain** code quality standards
- **Always prioritize** user experience
- **Always ensure** system reliability
- **Always improve** continuously

---

*This document is binding and non-negotiable*
*Last Updated: September 24, 2025*
*Version: 1.0*
*Next Review: October 24, 2025*
