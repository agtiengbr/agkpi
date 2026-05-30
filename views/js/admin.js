(function () {
  if (window.agkpisAdminInitialized) {
    return;
  }

  window.agkpisAdminInitialized = true;

  function scrollToTarget(event) {
    var trigger = event.target.closest('.js-agkpis-scroll');
    var target;

    if (!trigger) {
      return;
    }

    target = document.querySelector(trigger.getAttribute('href'));
    if (!target) {
      return;
    }

    event.preventDefault();
    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function getOrdersPanelConfig() {
    var cardKpis = document.querySelector('.card-kpis');
    var sourceRow;

    if (!cardKpis) {
      return null;
    }

    sourceRow = cardKpis.querySelector('.kpi-row');
    if (sourceRow) {
      return {
        anchor: cardKpis,
        sourceRow: sourceRow,
      };
    }

    sourceRow = cardKpis.querySelector('.orders-kpi .kpi-container .row')
      || cardKpis.querySelector('.orders-kpi .row.justify-content-around');

    if (!sourceRow) {
      return null;
    }

    return {
      anchor: cardKpis,
      sourceRow: sourceRow,
    };
  }

  function getCardLink(element) {
    if (!element) {
      return null;
    }

    if (element.classList && element.classList.contains('agkpis-card-link')) {
      return element;
    }

    return element.querySelector('.agkpis-card-link');
  }

  function getSourceItems(sourceRow) {
    return Array.prototype.slice.call(sourceRow.children).filter(function (child) {
      return !!getCardLink(child);
    });
  }

  function ensureOrdersPanel(anchor, panelTitle) {
    var panel = document.querySelector('.agkpis-orders-panel');
    var title;

    if (!panel) {
      panel = document.createElement('section');
      panel.className = 'agkpis-orders-panel';
      panel.innerHTML = '<div class="agkpis-orders-panel__header"><h3 class="agkpis-orders-panel__title"></h3></div>'
        + '<div class="agkpis-orders-panel__body"><div class="agkpis-orders-panel__row"></div></div>';
      anchor.insertAdjacentElement('afterend', panel);
    }

    title = panel.querySelector('.agkpis-orders-panel__title');
    if (title) {
      title.textContent = panelTitle;
    }

    return panel;
  }

  function moveOrdersCards() {
    var config;
    var panel;
    var targetRow;
    var panelTitle;
    var sourceItems;
    var existingCards;
    var allCards;
    var firstCard;

    config = getOrdersPanelConfig();
    if (!config) {
      return;
    }

    panel = document.querySelector('.agkpis-orders-panel');
    sourceItems = getSourceItems(config.sourceRow);
    existingCards = panel
      ? Array.prototype.slice.call(panel.querySelectorAll('.agkpis-card-link'))
      : [];
    allCards = sourceItems.length
      ? sourceItems.map(function (item) {
        return getCardLink(item);
      }).filter(function (card) {
        return !!card;
      })
      : existingCards;

    if (!allCards.length) {
      if (panel) {
        panel.remove();
      }
      return;
    }

    firstCard = allCards[0];
    panelTitle = firstCard.getAttribute('data-panel-title') || 'KPIs personalizados';
    panel = ensureOrdersPanel(config.anchor, panelTitle);
    targetRow = panel.querySelector('.agkpis-orders-panel__row');

    if (!sourceItems.length) {
      return;
    }

    while (targetRow.firstChild) {
      targetRow.removeChild(targetRow.firstChild);
    }

    sourceItems.forEach(function (item) {
      targetRow.appendChild(item);
    });
  }

  function initOrdersPanel() {
    var config = getOrdersPanelConfig();
    var observer;

    if (!config) {
      return;
    }

    moveOrdersCards();

    observer = new MutationObserver(function () {
      moveOrdersCards();
    });

    observer.observe(config.anchor, {
      childList: true,
      subtree: true,
    });
  }

  function init() {
    document.addEventListener('click', scrollToTarget);
    initOrdersPanel();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();