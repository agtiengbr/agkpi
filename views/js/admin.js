(function () {
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

  function ensureOrdersPanel(cardKpis, panelTitle) {
    var panel = document.querySelector('.agkpis-orders-panel');

    if (!panel) {
      panel = document.createElement('section');
      panel.className = 'card agkpis-orders-panel';
      panel.innerHTML = '<div class="card-header"><h3 class="card-header-title"></h3></div>'
        + '<div class="card-body"><div class="agkpis-orders-panel__row"></div></div>';
      cardKpis.insertAdjacentElement('afterend', panel);
    }

    panel.querySelector('.card-header-title').textContent = panelTitle;

    return panel;
  }

  function moveOrdersCards() {
    var cardKpis = document.querySelector('.card-kpis');
    var sourceRow;
    var panel;
    var targetRow;
    var panelTitle;
    var sourceCards;
    var existingCards;
    var allCards;

    if (!cardKpis) {
      return;
    }

    sourceRow = cardKpis.querySelector('.kpi-row');
    if (!sourceRow) {
      return;
    }

    panel = document.querySelector('.agkpis-orders-panel');
    sourceCards = Array.prototype.slice.call(sourceRow.querySelectorAll('.agkpis-card-link'));
    existingCards = panel
      ? Array.prototype.slice.call(panel.querySelectorAll('.agkpis-card-link'))
      : [];
    allCards = sourceCards.length ? sourceCards : existingCards;

    if (!allCards.length) {
      if (panel) {
        panel.remove();
      }
      return;
    }

    panelTitle = allCards[0].getAttribute('data-panel-title') || 'KPIs personalizados';
    panel = ensureOrdersPanel(cardKpis, panelTitle);
    targetRow = panel.querySelector('.agkpis-orders-panel__row');

    sourceCards.forEach(function (card) {
      targetRow.appendChild(card);
    });
  }

  function initOrdersPanel() {
    var sourceRow = document.querySelector('.card-kpis .kpi-row');
    var observer;

    if (!sourceRow) {
      return;
    }

    moveOrdersCards();

    observer = new MutationObserver(function () {
      moveOrdersCards();
    });

    observer.observe(sourceRow, {
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