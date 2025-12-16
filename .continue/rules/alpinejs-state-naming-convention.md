---
globs: frontend/src/components/**/*.{js,jsx,ts,tsx}
regex: useState
description: Ensures consistency in naming Alpine.js state variables, making the
  code more readable and maintainable. Applies to files containing Alpine.js
  components.
alwaysApply: false
---

Alpine.js state variables related to visibility should end with the word 'Open' or 'Visible'. For example, 'mobileMenuOpen' instead of 'showMobileMenu'.