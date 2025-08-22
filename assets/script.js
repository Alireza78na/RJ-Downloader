document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('download-form');
    const inputArea = document.getElementById('input-area');
    const submitBtn = document.getElementById('submit-btn');
    const loader = document.getElementById('loader');
    const resultsArea = document.getElementById('results-area');
    const themeToggle = document.getElementById('theme-toggle');
    const historyContainer = document.getElementById('history-buttons');
    let isLoading = false;
    let pageReady = false;
    window.addEventListener('load', () => {
        setTimeout(() => { pageReady = true; }, 100);
    });

    // --- Theme Manager ---
    const applyTheme = (theme) => {
        document.documentElement.setAttribute('data-theme', theme);
        themeToggle.innerHTML = `<svg width="24" height="24"><use href="${theme === 'dark' ? '#icon-sun' : '#icon-moon'}"/></svg>`;
    };
    const currentTheme = localStorage.getItem('theme') || 'dark';
    applyTheme(currentTheme);
    themeToggle.addEventListener('click', () => {
        const newTheme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        localStorage.setItem('theme', newTheme);
        applyTheme(newTheme);
    });

    // --- Toast Notifier ---
    const showToast = (message) => {
        const toast = document.getElementById('toast');
        toast.textContent = message; toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 3000);
    };

    // --- History Manager ---
    let history = JSON.parse(localStorage.getItem('rj_history')) || [];
    const updateHistory = (newItem) => {
        if (!history.find(h => h.source === newItem.source)) {
            history.unshift(newItem);
            history = history.slice(0, 5);
            localStorage.setItem('rj_history', JSON.stringify(history));
            renderHistory();
        }
    };
    const renderHistory = () => {
        historyContainer.innerHTML = history.length > 0 ? '<small>تاریخچه اخیر:</small>' : '';
        history.forEach(item => {
            const btn = document.createElement('button');
            let text;
            if (item.type === 'playlist') {
                text = `پلی‌لیست: ${item.playlist_name}`;
            } else if (item.source.includes('http')) {
                text = item.items[0].title;
            } else {
                text = `جستجو: ${item.query}`;
            }
            btn.textContent = text.substring(0, 35) + (text.length > 35 ? '...' : '');
            btn.title = text;
            btn.onclick = () => { inputArea.value = item.source; form.dispatchEvent(new Event('submit')); };
            historyContainer.appendChild(btn);
        });
    };
    renderHistory();

    // --- Form Submission (AJAX) ---
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!pageReady || isLoading || inputArea.value.trim() === '') return;

        isLoading = true;
        submitBtn.disabled = true;
        submitBtn.querySelector('span').textContent = 'در حال پردازش...';
        loader.style.display = 'block';
        resultsArea.innerHTML = '';

        const formData = new FormData();
        formData.append('input_data', inputArea.value);

        try {
            const response = await fetch('api.php', { method: 'POST', body: formData });
            if (!response.ok) {
               throw new Error(`Server responded with status: ${response.status}`);
            }
            const data = await response.json();

            if (data.results.length === 0 || data.results.every(r => r.error && !r.data)) {
                resultsArea.innerHTML = `<div class="empty-state"><p>نتیجه‌ای یافت نشد!</p><small>لطفاً ورودی خود را بررسی کرده و دوباره تلاش کنید.</small></div>`;
            } else {
                data.results.forEach(result => {
                    if (result.error) {
                        showToast(`خطا: ${result.error}`);
                    } else if (result.data) {
                            const groupHtml = renderResultGroup(result.data);
                            const tempDiv = document.createElement('div');
                            tempDiv.innerHTML = groupHtml;

                            // Animate cards
                            const cards = tempDiv.querySelectorAll('.media-card');
                            cards.forEach((card, index) => {
                                setTimeout(() => {
                                    card.classList.add('visible');
                                }, index * 100);
                            });

                            resultsArea.appendChild(tempDiv.firstElementChild);
                        if (result.data.type !== 'search') updateHistory(result.data);
                    }
                });
            }
        } catch (error) {
            console.error('Fetch Error:', error);
            showToast('خطای عمومی رخ داد. لطفاً اتصال خود را بررسی کنید.');
        } finally {
            isLoading = false;
            submitBtn.disabled = false;
            submitBtn.querySelector('span').textContent = 'پردازش';
            loader.style.display = 'none';
        }
    });

    // --- UI Rendering ---
    const renderResultGroup = (data) => {
        let header = `<div class="result-group-header">ورودی: ${data.source}</div>`;
        if (data.type === 'playlist') {
            header += `<button class="download-all-main-btn" data-group-id="${data.source}"><svg width="24" height="24"><use href="#icon-download"/></svg><span>دانلود همه (${data.items.length} فایل)</span></button>`;
        } else if (data.type === 'search') {
            header = `<div class="result-group-header">نتایج جستجو برای: "${data.query}"</div>`;
        }
        const itemsHTML = data.items.map(item => `
            <div class="media-card" data-group-id="${data.source}">
                <img src="${item.cover}" alt="Cover" class="media-cover" loading="lazy">
                <div class="media-info">
                    <h3>${item.title}</h3><p>${item.artist}</p>
                    <div class="media-actions">${generateActionButtons(item)}</div>
                </div>
            </div>`).join('');
        return `<div class="result-group">${header}${itemsHTML}</div>`;
    };

    const generateActionButtons = (item) => {
        const dlIcon = `<svg width="16" height="16"><use href="#icon-download"/></svg>`;
        const copyIcon = `<svg width="16" height="16"><use href="#icon-copy"/></svg>`;
        const telegramIcon = `<svg width="16" height="16"><use href="#icon-telegram"/></svg>`;
        let buttons = '';

        const createTelegramLink = (url, title) => {
            const text = `لینک دانلود "${title}"`;
            return `https://t.me/share/url?url=${encodeURIComponent(url)}&text=${encodeURIComponent(text)}`;
        };

        if (item.download_url) {
            buttons += `<a href="${item.download_url}" class="dl-btn" target="_blank" rel="noopener noreferrer">${dlIcon}دانلود</a>`;
            buttons += `<a href="${createTelegramLink(item.download_url, item.title)}" class="telegram-btn" target="_blank" rel="noopener noreferrer">${telegramIcon}تلگرام</a>`;
            buttons += `<button class="copy-btn" data-link="${item.download_url}">${copyIcon}کپی</button>`;
        } else if (item.qualities) {
            Object.entries(item.qualities).forEach(([quality, link]) => {
                buttons += `<a href="${link}" class="dl-btn" target="_blank" rel="noopener noreferrer">${dlIcon}${quality}</a>`;
            });
        }
        return buttons;
    };

    // --- Modal Manager ---
    const modal = document.getElementById('download-all-modal');
    const modalLinks = document.getElementById('download-all-links');
    const closeModalBtn = document.querySelector('.modal-close-btn');
    const copyAllBtn = document.getElementById('copy-all-btn');

    const showModal = () => modal.classList.remove('hidden');
    const hideModal = () => modal.classList.add('hidden');

    closeModalBtn.addEventListener('click', hideModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) hideModal();
    });
    copyAllBtn.addEventListener('click', () => {
        modalLinks.select();
        navigator.clipboard.writeText(modalLinks.value).then(() => {
            showToast('تمام لینک‌ها با موفقیت کپی شد!');
            hideModal();
        });
    });

    // --- Event Delegation for Dynamic Buttons ---
    resultsArea.addEventListener('click', (e) => {
        const copyBtn = e.target.closest('.copy-btn');
        const downloadAllBtn = e.target.closest('.download-all-main-btn');

        if (copyBtn) {
            navigator.clipboard.writeText(copyBtn.dataset.link).then(() => showToast('لینک با موفقیت کپی شد!'));
        }

        if (downloadAllBtn && !downloadAllBtn.disabled) {
            const groupId = downloadAllBtn.dataset.groupId;
            const links = Array.from(document.querySelectorAll(`.media-card[data-group-id="${groupId}"] .dl-btn`));
            const allLinksText = links.map(link => link.href).join('\n');

            modalLinks.value = allLinksText;
            showModal();
            modalLinks.select();
            showToast(`تمام ${links.length} لینک آماده کپی است.`);
        }
    });
});
