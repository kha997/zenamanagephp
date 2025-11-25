import React, { useEffect, useState } from 'react';

export default function TeamPage() {
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  return (
    <div>
      <h1>Team</h1>
      <p>This is the team page.</p>
      {mounted && <p>Client-side rendering enabled!</p>}
    </div>
  );
}

