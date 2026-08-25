(() => {
  'use strict';

  const root = document.querySelector('[data-mobile-landing-media]');
  if (!root) return;

  const list = root.querySelector('[data-landing-media-list]');
  const input = root.querySelector('[data-landing-media-input]');
  const choose = root.querySelector('[data-landing-media-choose]');
  const empty = root.querySelector('[data-landing-media-empty]');
  const status = root.querySelector('[data-landing-media-status]');
  const template = root.querySelector('[data-landing-media-template]');
  const maximum = Math.max(1, Number(root.dataset.maxImages || 6));
  if (!list || !input || !choose || !empty || !template) return;

  const items = () => Array.from(list.querySelectorAll('[data-landing-media-item]'));
  const sync = (message = '') => {
    const cards = items();
    input.value = cards.map((card) => card.dataset.id || '').filter(Boolean).join(',');
    empty.hidden = cards.length > 0;
    choose.disabled = cards.length >= maximum;
    if (status && message) status.textContent = message;
  };

  const appendAttachment = (attachment) => {
    const id = Number(attachment?.id || 0);
    if (!Number.isInteger(id) || id < 1) return false;
    if (items().some((card) => Number(card.dataset.id) === id)) return false;
    if (items().length >= maximum) return false;

    const fragment = template.content.cloneNode(true);
    const card = fragment.querySelector('[data-landing-media-item]');
    const image = fragment.querySelector('img');
    const name = fragment.querySelector('[data-landing-media-name]');
    const mediaId = fragment.querySelector('[data-landing-media-id]');
    if (!card || !image || !name || !mediaId) return false;

    const sizes = attachment.sizes || {};
    const preview = sizes.medium?.url || sizes.large?.url || attachment.url || '';
    const label = String(attachment.alt || attachment.caption || attachment.title || `#${id}`).trim();
    card.dataset.id = String(id);
    image.src = preview;
    image.alt = String(attachment.alt || '');
    name.textContent = label;
    mediaId.textContent = `${root.dataset.mediaIdLabel || 'Media ID'}: ${id}`;
    list.appendChild(fragment);
    return true;
  };

  list.addEventListener('click', (event) => {
    const button = event.target instanceof Element ? event.target.closest('button') : null;
    const card = button?.closest('[data-landing-media-item]');
    if (!button || !card) return;

    if (button.matches('[data-landing-media-remove]')) {
      card.remove();
      sync();
      return;
    }
    if (button.matches('[data-landing-media-up]') && card.previousElementSibling) {
      list.insertBefore(card, card.previousElementSibling);
      sync();
      card.querySelector('[data-landing-media-up]')?.focus();
      return;
    }
    if (button.matches('[data-landing-media-down]') && card.nextElementSibling) {
      list.insertBefore(card.nextElementSibling, card);
      sync();
      card.querySelector('[data-landing-media-down]')?.focus();
    }
  });

  choose.addEventListener('click', () => {
    if (!window.wp?.media || items().length >= maximum) return;
    const frame = window.wp.media({
      title: root.dataset.frameTitle || 'Choose landing page images',
      button: {text: root.dataset.frameButton || 'Use selected images'},
      library: {type: 'image'},
      multiple: 'add',
    });
    frame.on('select', () => {
      let added = 0;
      frame.state().get('selection').each((model) => {
        if (appendAttachment(model.toJSON())) added += 1;
      });
      const reachedLimit = items().length >= maximum;
      const addedMessage = (root.dataset.addedMessage || 'Added %d image(s).')
        .replace('%d', String(added));
      sync(reachedLimit ? root.dataset.limitMessage || '' : addedMessage);
    });
    frame.open();
  });

  sync();
})();
