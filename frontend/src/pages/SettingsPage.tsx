import React, { useEffect, useState } from 'react';

export default function SettingsPage() {
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  return (
    <div>
      <h1>Settings</h1>
      <p>This is the settings page.</p>
      {mounted && <p>Client-side rendering enabled!</p>}
    </div>
  );
}

