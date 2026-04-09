import '../css/app.css';

const userMenus = document.querySelectorAll('[data-user-menu]');

userMenus.forEach((menu) => {
  const toggle = menu.querySelector('[data-user-menu-toggle]');
  const panel = menu.querySelector('[data-user-menu-panel]');

  if (!toggle || !panel) {
    return;
  }

  const closeAllMenus = () => {
    userMenus.forEach((otherMenu) => {
      const otherToggle = otherMenu.querySelector('[data-user-menu-toggle]');
      const otherPanel = otherMenu.querySelector('[data-user-menu-panel]');
      if (!otherToggle || !otherPanel) {
        return;
      }

      otherToggle.setAttribute('aria-expanded', 'false');
      otherPanel.hidden = true;
    });
  };

  const openMenu = () => {
    closeAllMenus();
    toggle.setAttribute('aria-expanded', 'true');
    panel.hidden = false;
  };

  const closeMenu = () => {
    toggle.setAttribute('aria-expanded', 'false');
    panel.hidden = true;
  };

  const isOpen = () => !panel.hidden;

  closeMenu();

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
