import '../css/app.css';

const cardTables = document.querySelectorAll('table.data-table-cards');

cardTables.forEach((table) => {
  const headers = Array.from(table.querySelectorAll('thead th')).map((th) =>
    (th.textContent || '').trim()
  );

  const rows = Array.from(table.querySelectorAll('tbody tr'));

  rows.forEach((row) => {
    const cells = Array.from(row.querySelectorAll('td'));
    if (cells.length === 0) {
      return;
    }

    if (cells.length === 1 && cells[0].hasAttribute('colspan')) {
      if (!cells[0].dataset.label) {
        cells[0].dataset.label = 'Info';
      }
      return;
    }

    cells.forEach((cell, index) => {
      if (!cell.dataset.label) {
        cell.dataset.label = headers[index] || `Field ${index + 1}`;
      }
    });
  });
});

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
