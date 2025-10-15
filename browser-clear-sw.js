navigator.service Worker.getRegistrations().then(r => r.forEach(x => x.unregister()))
