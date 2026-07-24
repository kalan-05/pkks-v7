(() => {
  'use strict';

  const DOWNLOAD_FILE_NAME = 'rekvizity-pravovaya-kontora-k-sopracheva.txt';
  const COPY_ERROR_MESSAGE = 'Не удалось скопировать. Выделите реквизиты вручную.';

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

  const setStatus = (card, message, state) => {
    const status = card.querySelector('[data-requisites-status]');

    if (!status) {
      return;
    }

    status.textContent = message;
    status.dataset.state = state;
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

    copyButton.addEventListener('click', async () => {
      try {
        const requisitesText = createRequisitesText(card);
        const copied = await copyText(requisitesText, copyButton);

        if (!copied) {
          throw new Error('Fallback копирования вернул неуспешный результат');
        }

        setStatus(card, 'Реквизиты скопированы', 'success');
      } catch (error) {
        setStatus(card, COPY_ERROR_MESSAGE, 'error');
      }
    });

    downloadButton.addEventListener('click', () => {
      try {
        downloadText(createRequisitesText(card));
        setStatus(card, 'Файл с реквизитами скачивается', 'success');
      } catch (error) {
        setStatus(card, 'Не удалось подготовить файл с реквизитами.', 'error');
      }
    });
  });
})();
