// ... existing code ...

/**
 * Tạo token cho user (dành cho testing)
 * 
 * @param User $user
 * @return string
 */
public function createTokenForUser(User $user): string
{
    return $this->generateToken($user);
}

// ... existing code ...