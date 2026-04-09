import '../css/app.css';

const userMenus = document.querySelectorAll('[data-user-menu]');

userMenus.forEach((menu) => {
  const toggle = menu.querySelector('[data-user-menu-toggle]');
  const panel = menu.querySelector('[data-user-menu-panel]');

  if (!toggle || !panel) {
    return;
  }

  const openMenu = () => {
    toggle.setAttribute('aria-expanded', 'true');
    panel.classList.remove('pointer-events-none', 'opacity-0', 'translate-y-1', 'scale-[0.98]');
    panel.classList.add('pointer-events-auto', 'opacity-100', 'translate-y-0', 'scale-100');
  };

  const closeMenu = () => {
    toggle.setAttribute('aria-expanded', 'false');
    panel.classList.remove('pointer-events-auto', 'opacity-100', 'translate-y-0', 'scale-100');
    panel.classList.add('pointer-events-none', 'opacity-0', 'translate-y-1', 'scale-[0.98]');
  };

  const isOpen = () => toggle.getAttribute('aria-expanded') === 'true';

  toggle.addEventListener('click', (event) => {
    event.preventDefault();
    if (isOpen()) {
      closeMenu();
    } else {
      openMenu();
    }
  });

  document.addEventListener('click', (event) => {
    if (!menu.contains(event.target)) {
      closeMenu();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeMenu();
    }
  });

  panel.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => closeMenu());
  });
});
