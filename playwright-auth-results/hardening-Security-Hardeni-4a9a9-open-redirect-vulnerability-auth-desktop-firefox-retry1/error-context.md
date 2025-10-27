# Page snapshot

```yaml
- generic [ref=e4]:
  - generic [ref=e5]:
    - img [ref=e7]
    - heading "Sign in to your account" [level=2] [ref=e9]
    - paragraph [ref=e10]:
      - text: Or
      - link "create a new account" [ref=e11] [cursor=pointer]:
        - /url: http://127.0.0.1:8000/register
  - generic [ref=e12]:
    - generic [ref=e13]:
      - generic [ref=e14]:
        - generic [ref=e15]: Email address
        - textbox "Email address" [ref=e16]: test@test.com
      - generic [ref=e17]:
        - generic [ref=e18]: Password
        - textbox "Password" [ref=e19]: password
        - button [ref=e20] [cursor=pointer]:
          - img [ref=e21]
    - generic [ref=e24]:
      - generic [ref=e25]:
        - checkbox "Remember me" [ref=e26]
        - generic [ref=e27]: Remember me
      - link "Forgot your password?" [ref=e29] [cursor=pointer]:
        - /url: http://127.0.0.1:8000/password/reset
    - button "Sign in" [ref=e31] [cursor=pointer]:
      - img [ref=e33]
      - generic [ref=e35]: Sign in
  - generic [ref=e37]:
    - img [ref=e39]
    - paragraph [ref=e42]: IP address temporarily blocked due to suspicious activity. Please try again in 15 minutes.
```