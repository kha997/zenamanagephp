import React, { useEffect, useState } from 'react';

export default function CalendarPage() {
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  return (
    <div>
      <h1>Calendar</h1>
      <p>This is the calendar page.</p>
      {mounted && <p>Client-side rendering enabled!</p>}
    </div>
  );
}

