(() => {
  'use strict';

  const DOWNLOAD_FILE_NAME = 'rekvizity-pravovaya-kontora-k-sopracheva.txt';
  const COPY_ERROR_MESSAGE = 'Не удалось скопировать. Выделите реквизиты вручную.';
  const SUCCESS_FEEDBACK_TIMEOUT = 3000;
  const ERROR_FEEDBACK_TIMEOUT = 6000;
  const SCROLL_DISMISS_THRESHOLD = 20;
  const SCROLL_DISMISS_GRACE_PERIOD = 750;
  const feedbackStates = new WeakMap();

  const normalizeText = (value) => value.replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim();

  const getFieldText = (card, fieldName) => {
    const field = card.querySelector(`[data-requisites-field="${fieldName}"]`);

    if (!field) {
      throw new Error(`Не найдено поле реквизитов: ${fieldName}`);
    }

    return normalizeText(field.innerText);
  };

  const getValueAfterLabel = (fieldText) => {
    const separatorIndex = fieldText.indexOf(':');
    return separatorIndex === -1 ? '' : fieldText.slice(separatorIndex + 1).trim();
  };

  const getPairValues = (fieldText, firstLabel, secondLabel) => {
    const expression = new RegExp(`^${firstLabel}\\s+(.+?)\\s+${secondLabel}\\s+(.+)$`);
    const match = fieldText.match(expression);

    if (!match) {
      throw new Error(`Не удалось разобрать поля ${firstLabel} и ${secondLabel}`);
    }

    return [match[1], match[2]];
  };

  const createRequisitesText = (card) => {
    const company = getValueAfterLabel(getFieldText(card, 'company'));
    const address = getValueAfterLabel(getFieldText(card, 'address'));
    const phone = getValueAfterLabel(getFieldText(card, 'phone'));
    const email = getValueAfterLabel(getFieldText(card, 'email'));
    const [ogrn, inn] = getPairValues(getFieldText(card, 'registration'), 'ОГРН', 'ИНН');
    const [kpp, okpo] = getPairValues(getFieldText(card, 'statistics'), 'КПП', 'ОКПО');
    const bankMatch = getFieldText(card, 'bank').match(/^р\/с\s+(.+?)\s+в\s+(.+?),\s*к\/с\s+(.+?),\s*БИК\s+(.+)$/i);

    if (!company || !address || !phone || !email || !bankMatch) {
      throw new Error('Не удалось собрать текст реквизитов из карточки');
    }

    return [
      company,
      `Адрес: ${address}`,
      `Телефон: ${phone}`,
      `E-mail: ${email}`,
      `ОГРН: ${ogrn}`,
      `ИНН: ${inn}`,
      `КПП: ${kpp}`,
      `ОКПО: ${okpo}`,
      `Расчётный счёт: ${bankMatch[1]}`,
      `Банк: ${bankMatch[2]}`,
      `Корреспондентский счёт: ${bankMatch[3]}`,
      `БИК: ${bankMatch[4]}`,
      `Генеральный директор: ${getValueAfterLabel(getFieldText(card, 'director'))}`,
      `Система налогообложения: ${getValueAfterLabel(getFieldText(card, 'taxation'))}`
    ].join('\n');
  };

  const getFeedbackState = (card) => {
    if (!feedbackStates.has(card)) {
      feedbackStates.set(card, { timerId: null, scrollPosition: window.scrollY, scrollReadyAt: 0 });
    }

    return feedbackStates.get(card);
  };

  const clearFeedbackTimer = (card) => {
    const feedbackState = getFeedbackState(card);

    if (feedbackState.timerId !== null) {
      window.clearTimeout(feedbackState.timerId);
      feedbackState.timerId = null;
    }
  };

  const hideFeedback = (card) => {
    const status = card.querySelector('[data-requisites-status]');

    clearFeedbackTimer(card);

    if (!status || !status.textContent) {
      return;
    }

    status.textContent = '';
    delete status.dataset.state;
  };

  const showFeedback = (card, message, state) => {
    const status = card.querySelector('[data-requisites-status]');

    if (!status) {
      return;
    }

    clearFeedbackTimer(card);
    status.textContent = message;
    status.dataset.state = state;

    const feedbackState = getFeedbackState(card);
    const timeout = state === 'error' ? ERROR_FEEDBACK_TIMEOUT : SUCCESS_FEEDBACK_TIMEOUT;

    feedbackState.scrollPosition = window.scrollY;
    feedbackState.scrollReadyAt = Date.now() + SCROLL_DISMISS_GRACE_PERIOD;
    feedbackState.timerId = window.setTimeout(() => hideFeedback(card), timeout);
  };

  const copyWithFallback = (text, trigger) => {
    const textarea = document.createElement('textarea');
    textarea.className = 'details-copy-buffer';
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.setAttribute('aria-hidden', 'true');
    document.body.append(textarea);
    textarea.select();
    textarea.setSelectionRange(0, textarea.value.length);

    try {
      return document.execCommand('copy');
    } finally {
      textarea.remove();
      trigger.focus();
    }
  };

  const copyText = async (text, trigger) => {
    if (navigator.clipboard && window.isSecureContext) {
      try {
        await navigator.clipboard.writeText(text);
        return true;
      } catch (error) {
        // Clipboard API может быть запрещён политикой браузера, используем локальный fallback.
      }
    }

    return copyWithFallback(text, trigger);
  };

  const downloadText = (text) => {
    const blob = new Blob([`\uFEFF${text}`], { type: 'text/plain;charset=utf-8' });
    const objectUrl = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = objectUrl;
    link.download = DOWNLOAD_FILE_NAME;
    link.className = 'details-copy-buffer';
    link.setAttribute('aria-hidden', 'true');
    document.body.append(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(objectUrl), 0);
  };

  document.querySelectorAll('[data-requisites-card]').forEach((card) => {
    const actions = card.querySelector('[data-requisites-actions]');
    const copyButton = card.querySelector('[data-requisites-copy]');
    const downloadButton = card.querySelector('[data-requisites-download]');

    if (!actions || !copyButton || !downloadButton) {
      return;
    }

    actions.hidden = false;

    // Слушатели создаются один раз для карточки и скрывают только активный feedback.
    window.addEventListener('scroll', () => {
      const feedbackState = getFeedbackState(card);

      if (Date.now() < feedbackState.scrollReadyAt) {
        feedbackState.scrollPosition = window.scrollY;
        return;
      }

      if (Math.abs(window.scrollY - feedbackState.scrollPosition) >= SCROLL_DISMISS_THRESHOLD) {
        hideFeedback(card);
      }
    }, { passive: true });

    document.addEventListener('pointerdown', (event) => {
      if (!card.contains(event.target)) {
        hideFeedback(card);
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        hideFeedback(card);
      }
    });

    copyButton.addEventListener('click', async () => {
      try {
        const requisitesText = createRequisitesText(card);
        const copied = await copyText(requisitesText, copyButton);

        if (!copied) {
          throw new Error('Fallback копирования вернул неуспешный результат');
        }

        showFeedback(card, 'Реквизиты скопированы', 'success');
      } catch (error) {
        showFeedback(card, COPY_ERROR_MESSAGE, 'error');
      }
    });

    downloadButton.addEventListener('click', () => {
      try {
        downloadText(createRequisitesText(card));
        showFeedback(card, 'Файл с реквизитами скачивается', 'success');
      } catch (error) {
        showFeedback(card, 'Не удалось подготовить файл с реквизитами.', 'error');
      }
    });
  });
})();
