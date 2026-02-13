/**
 * Yandex Maps Reviews Parser (stealth, no 2captcha)
 * 
 * Requirements:
 *   npm install puppeteer-extra puppeteer-extra-plugin-stealth puppeteer-core
 *   google-chrome-stable installed
 *
 * Usage:
 *   node parse_reviews.js <url> [cacheDir] [cacheKey]
 *   node parse_reviews.js "https://yandex.ru/maps/org/italy/1248026929/reviews/" ./cache italy
 *
 * Optional env:
 *   PROXY=http://user:pass@host:port
 */

const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
const fs = require('fs');
const path = require('path');

puppeteer.use(StealthPlugin());

const delay = (ms) => new Promise(resolve => setTimeout(resolve, ms));
const PAGE_SIZE = 50;
const log = (msg) => process.stderr.write(msg + '\n');

(async () => {
    const url = process.argv[2];
    const cacheDir = process.argv[3];
    const cacheKey = process.argv[4];

    if (!url) { console.error(JSON.stringify({ error: 'URL not provided' })); process.exit(1); }

    const saveToDisk = !!(cacheDir && cacheKey);
    if (saveToDisk) fs.mkdirSync(cacheDir, { recursive: true });

    // === Helpers ===
    function savePageToDisk(pageNum, reviews) {
        if (!saveToDisk) return;
        fs.writeFileSync(path.join(cacheDir, cacheKey + '_page_' + pageNum + '.json'), JSON.stringify({
            cached_at: new Date().toISOString(), page: pageNum, per_page: PAGE_SIZE, reviews
        }, null, 2));
    }

    function saveMetaToDisk(organization, totalParsed, isComplete) {
        if (!saveToDisk) return;
        fs.writeFileSync(path.join(cacheDir, cacheKey + '_meta.json'), JSON.stringify({
            cached_at: new Date().toISOString(), organization,
            total_expected: isComplete ? totalParsed : (organization.review_count || totalParsed),
            total_parsed: totalParsed, total_pages: Math.ceil(totalParsed / PAGE_SIZE), is_complete: isComplete
        }, null, 2));
    }

    function mapApiReview(r, idx) {
        const dateStr = r.updatedTime || '';
        let published = '';
        if (dateStr) {
            try {
                const d = new Date(dateStr);
                const months = ['января','февраля','марта','апреля','мая','июня','июля','августа','сентября','октября','ноября','декабря'];
                published = d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
            } catch(e) { published = dateStr; }
        }
        return {
            id: r.reviewId || ('review_' + idx),
            author: r.author?.name || 'Аноним',
            text: (r.text || '').trim(),
            rating: r.rating || 0,
            published_at: published
        };
    }

    let lastSavedCount = 0;
    let currentPageNum = 0;
    var orgData = { name: '', rating: 0, review_count: 0 };

    function saveProgress(allReviews, isComplete) {
        // Сохраняем полные страницы
        while (lastSavedCount + PAGE_SIZE <= allReviews.length) {
            currentPageNum++;
            savePageToDisk(currentPageNum, allReviews.slice(lastSavedCount, lastSavedCount + PAGE_SIZE));
            lastSavedCount += PAGE_SIZE;
        }
        // Сохраняем неполную страницу (partial flush) — чтобы PHP мог читать сразу
        if (lastSavedCount < allReviews.length) {
            currentPageNum++;
            savePageToDisk(currentPageNum, allReviews.slice(lastSavedCount));
            lastSavedCount = allReviews.length;
        }
        saveMetaToDisk(orgData, allReviews.length, isComplete);
    }

    // === Загрузка кэша ===
    const allReviews = [];
    const seenIds = new Set();

    if (saveToDisk) {
        try {
            const files = fs.readdirSync(cacheDir).filter(f => f.startsWith(cacheKey + '_page_') && f.endsWith('.json')).sort();
            for (const f of files) {
                const data = JSON.parse(fs.readFileSync(path.join(cacheDir, f), 'utf8'));
                if (data.reviews && Array.isArray(data.reviews)) {
                    for (const r of data.reviews) {
                        if (r.id && !seenIds.has(r.id)) {
                            seenIds.add(r.id);
                            allReviews.push(r);
                        }
                    }
                }
            }
            if (allReviews.length > 0) {
                lastSavedCount = 0;
                currentPageNum = 0;
                log('[cache] Loaded ' + allReviews.length + ' reviews from ' + files.length + ' files');
            }
        } catch(e) {}
    }

    let browser;
    const profileDir = '/tmp/chrome-profile-' + process.pid;

    try {
        fs.mkdirSync(profileDir + '/data', { recursive: true });
        process.env.HOME = '/tmp';

        let executablePath = '/usr/bin/google-chrome-stable';
        if (!fs.existsSync(executablePath)) executablePath = '/usr/bin/chromium-browser';
        if (!fs.existsSync(executablePath)) executablePath = '/usr/bin/chromium';

        const launchArgs = [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--no-first-run',
            '--no-default-browser-check',
            '--disable-extensions',
            '--disable-blink-features=AutomationControlled',
            '--user-data-dir=' + profileDir + '/data',
            '--lang=ru-RU',
        ];

        const proxyUrl = process.env.PROXY || '';
        if (proxyUrl) {
            launchArgs.push('--proxy-server=' + proxyUrl);
            log('[proxy] ' + proxyUrl);
        }

        browser = await puppeteer.launch({
            headless: 'new',
            executablePath,
            userDataDir: profileDir + '/data',
            args: launchArgs,
            env: { ...process.env, HOME: '/tmp', TMPDIR: '/tmp' }
        });

        const page = await browser.newPage();
        await page.setViewport({ width: 1366, height: 768 });
        await page.setUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36');
        await page.setExtraHTTPHeaders({ 'Accept-Language': 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7' });

        // Дополнительная маскировка
        await page.evaluateOnNewDocument(() => {
            Object.defineProperty(navigator, 'webdriver', { get: () => false });
            Object.defineProperty(navigator, 'plugins', { get: () => [1, 2, 3, 4, 5].map(() => ({ length: 1 })) });
            Object.defineProperty(navigator, 'languages', { get: () => ['ru-RU', 'ru', 'en-US', 'en'] });
            window.chrome = { runtime: {} };
        });

        // === Обработка капчи (клик + ожидание) ===
        async function handleCaptcha() {
            const hasCaptcha = await page.evaluate(() => {
                return document.querySelector('.CheckboxCaptcha') !== null
                    || document.body?.innerText?.includes('Я не робот')
                    || document.body?.innerText?.includes('SmartCaptcha');
            });
            if (!hasCaptcha) return false;

            log('[captcha] Detected! Trying to click...');

            // Попытка кликнуть чекбокс
            try {
                const clicked = await page.evaluate(() => {
                    const btn = document.querySelector('.CheckboxCaptcha-Button')
                        || document.querySelector('.CheckboxCaptcha input[type="submit"]')
                        || document.querySelector('button[type="submit"]')
                        || document.querySelector('.SmartCaptcha-Button');
                    if (btn) { btn.click(); return true; }

                    // Попробуем найти iframe и кликнуть внутри
                    const form = document.querySelector('form');
                    if (form) {
                        const submit = form.querySelector('[type="submit"], button');
                        if (submit) { submit.click(); return true; }
                    }
                    return false;
                });

                if (clicked) {
                    log('[captcha] Clicked, waiting 10s...');
                    await delay(10000);

                    const still = await page.evaluate(() => {
                        return document.querySelector('.CheckboxCaptcha') !== null
                            || document.body?.innerText?.includes('Я не робот');
                    });
                    if (!still) {
                        log('[captcha] Solved by click!');
                        return true;
                    }
                }
            } catch(e) {}

            // Ждём и перезагружаем
            log('[captcha] Waiting 120s for cooldown...');
            await delay(120000);
            try {
                await page.reload({ waitUntil: 'networkidle2', timeout: 60000 });
                await delay(3000);
            } catch(e) {}

            const stillHas = await page.evaluate(() => {
                return document.querySelector('.CheckboxCaptcha') !== null
                    || document.body?.innerText?.includes('Я не робот');
            });

            if (stillHas) {
                log('[captcha] Still present after wait');
                return false; // не решена
            }
            log('[captcha] Cleared after wait');
            return true;
        }

        // === Безопасная навигация ===
        async function safeGoto(targetUrl, maxRetries) {
            for (let i = 0; i < maxRetries; i++) {
                try {
                    await page.goto(targetUrl, { waitUntil: 'networkidle2', timeout: 60000 });
                    await delay(2000 + Math.random() * 2000);

                    const hasCaptcha = await page.evaluate(() => {
                        return document.querySelector('.CheckboxCaptcha') !== null
                            || document.body?.innerText?.includes('Я не робот')
                            || document.body?.innerText?.includes('SmartCaptcha');
                    });

                    if (!hasCaptcha) return true;

                    log('[captcha] On load attempt ' + (i + 1));
                    const solved = await handleCaptcha();
                    if (solved) return true;
                } catch(e) {
                    log('[goto] Error: ' + e.message);
                    await delay(10000);
                }
            }
            return false;
        }

        // === Перехватчик fetchReviews ===
        const capturedApiParams = {};

        page.on('request', (request) => {
            if (!request.url().includes('/api/business/fetchReviews')) return;
            try {
                const u = new URL(request.url());
                const ranking = u.searchParams.get('ranking') || 'by_relevance_org';
                capturedApiParams[ranking] = {
                    businessId: u.searchParams.get('businessId'),
                    csrfToken: u.searchParams.get('csrfToken'),
                    sessionId: u.searchParams.get('sessionId'),
                    reqId: u.searchParams.get('reqId'),
                    s: u.searchParams.get('s'),
                    locale: u.searchParams.get('locale') || 'ru_RU',
                };
                log('[intercepted] ranking=' + ranking + ' s=' + capturedApiParams[ranking].s);
            } catch(e) {}
        });

        const reviewsUrl = url.replace(/\/$/, '').replace(/\/reviews\/?$/, '') + '/reviews/';

        // ========================================
        // ШАГ 1: Прогрев сессии + загрузка страницы
        // ========================================
        log('\n=== STEP 1: Loading page ===');

        // Прогрев — заходим на карты как обычный пользователь
        log('[warmup] Opening yandex.ru/maps...');
        await safeGoto('https://yandex.ru/maps/', 2);
        await delay(3000 + Math.random() * 3000);

        // Переход на страницу отзывов
        log('[load] Opening reviews page...');
        const loaded = await safeGoto(reviewsUrl, 3);
        if (!loaded) {
            log('ERROR: Could not load reviews page');
            console.log(JSON.stringify({ organization: orgData, reviews: allReviews, total_pages: Math.ceil(allReviews.length / PAGE_SIZE) }));
            process.exit(1);
        }

        // Org data
        orgData = await page.evaluate(() => {
            const name = document.querySelector('.orgpage-header-view__header')?.textContent?.trim() || '';
            const ratingText = document.querySelector('.business-summary-rating-badge-view__rating-text')?.textContent?.trim() || '0';
            const rc = document.querySelector('meta[itemprop="reviewCount"]')?.getAttribute('content')
                    || document.querySelector('meta[itemProp="reviewCount"]')?.getAttribute('content');
            return { name, rating: parseFloat(ratingText), review_count: parseInt(rc || '0') };
        });
        const totalCount = orgData.review_count || 0;
        log('Org: ' + orgData.name + ', rating=' + orgData.rating + ', reviews=' + totalCount);

        // SSR отзывы первой страницы
        const ssrReviews = await page.evaluate(() => {
            const el = document.querySelector('script.state-view');
            if (!el) return [];
            try {
                const state = JSON.parse(el.textContent);
                function find(obj, d) {
                    if (d > 15 || !obj) return null;
                    if (typeof obj === 'object' && !Array.isArray(obj)) {
                        if (obj.reviews && Array.isArray(obj.reviews) && obj.params?.count) return obj.reviews;
                        for (const k of Object.keys(obj)) { const r = find(obj[k], d+1); if (r) return r; }
                    } else if (Array.isArray(obj)) {
                        for (const i of obj) { const r = find(i, d+1); if (r) return r; }
                    }
                    return null;
                }
                return find(state, 0) || [];
            } catch(e) { return []; }
        });

        for (const r of ssrReviews) {
            if (r.reviewId && !seenIds.has(r.reviewId)) {
                seenIds.add(r.reviewId);
                allReviews.push(mapApiReview(r, allReviews.length));
            }
        }
        log('[ssr] Initial: ' + allReviews.length + ' reviews');
        saveProgress(allReviews, false);

        // ========================================
        // ШАГ 2: Перехват API params для всех rankings
        // ========================================
        log('\n=== STEP 2: Intercepting API params ===');

        // Скролл чтобы триггернуть fetchReviews
        async function scrollToTriggerApi(targetRanking) {
            const cp = await page.evaluate(() => {
                const c = document.querySelector('.scroll__container');
                if (c) { const r = c.getBoundingClientRect(); return { x: r.x + r.width/2, y: r.y + r.height/2 }; }
                return null;
            });
            if (!cp) return false;
            await page.mouse.move(cp.x, cp.y);
            for (let i = 0; i < 15; i++) {
                await page.mouse.wheel({ deltaY: 4000 });
                await delay(1000 + Math.random() * 1000);
                if (targetRanking ? capturedApiParams[targetRanking] : Object.keys(capturedApiParams).length > 0) return true;
            }
            return targetRanking ? !!capturedApiParams[targetRanking] : Object.keys(capturedApiParams).length > 0;
        }

        // Переключение сортировки через UI
        async function switchSortUI(targetText) {
            for (let attempt = 0; attempt < 3; attempt++) {
                try {
                    await page.evaluate(() => {
                        const c = document.querySelector('.scroll__container');
                        if (c) c.scrollTop = 0;
                    });
                    await delay(1000);

                    await page.click('.rating-ranking-view');
                    await delay(1500);

                    const selected = await page.evaluate((text) => {
                        const lines = document.querySelectorAll('.rating-ranking-view__popup-line');
                        for (const line of lines) {
                            if (line.textContent?.trim()?.includes(text)) { line.click(); return true; }
                        }
                        return false;
                    }, targetText);

                    if (selected) {
                        await delay(2000 + Math.random() * 2000);
                        return true;
                    }
                    await page.keyboard.press('Escape');
                    await delay(500);
                } catch(e) { await delay(2000); }
            }
            return false;
        }

        // Первый скролл
        if (!Object.keys(capturedApiParams).length) {
            log('[scroll] Triggering first API request...');
            await scrollToTriggerApi(null);
        }

        // Переключаем все сортировки
        const sortMap = [
            { ranking: 'by_time', ui: 'новизн' },
            { ranking: 'by_rating_asc', ui: 'отрицательн' },
            { ranking: 'by_rating_desc', ui: 'положительн' },
        ];

        for (const { ranking, ui } of sortMap) {
            if (capturedApiParams[ranking]) continue;
            log('[sort] Switching to ' + ranking + '...');
            const switched = await switchSortUI(ui);
            if (switched && !capturedApiParams[ranking]) {
                await scrollToTriggerApi(ranking);
            }
        }

        log('\nCaptured: ' + Object.keys(capturedApiParams).join(', '));

        if (!Object.keys(capturedApiParams).length) {
            log('ERROR: No API params captured');
            console.log(JSON.stringify({ organization: orgData, reviews: allReviews, total_pages: Math.ceil(allReviews.length / PAGE_SIZE) }));
            process.exit(1);
        }

        // ========================================
        // ШАГ 3: Прямые API запросы
        // ========================================
        log('\n=== STEP 3: Direct API pagination ===');

        const rankings = ['by_relevance_org', 'by_time', 'by_rating_desc', 'by_rating_asc'];

        for (const ranking of rankings) {
            if (allReviews.length >= totalCount) break;

            const params = capturedApiParams[ranking];
            if (!params) { log('\n[' + ranking + '] No params, skip'); continue; }

            log('\n--- API: ' + ranking + ' ---');

            let dupeStreak = 0;
            let errStreak = 0;
            const maxPages = Math.ceil(totalCount / PAGE_SIZE) + 5;

            for (let p = 1; p <= maxPages; p++) {
                if (allReviews.length >= totalCount) { log('All reviews collected!'); break; }

                const apiUrl = 'https://yandex.ru/maps/api/business/fetchReviews' +
                    '?ajax=1&businessId=' + encodeURIComponent(params.businessId) +
                    '&csrfToken=' + encodeURIComponent(params.csrfToken) +
                    '&locale=' + encodeURIComponent(params.locale) +
                    '&page=' + p +
                    '&pageSize=' + PAGE_SIZE +
                    '&ranking=' + encodeURIComponent(ranking) +
                    '&reqId=' + encodeURIComponent(params.reqId) +
                    '&s=' + encodeURIComponent(params.s) +
                    '&sessionId=' + encodeURIComponent(params.sessionId);

                try {
                    const result = await page.evaluate(async (u) => {
                        try {
                            const r = await fetch(u, { credentials: 'include' });
                            if (!r.ok) return { error: r.status };
                            return await r.json();
                        } catch(e) { return { error: e.message }; }
                    }, apiUrl);

                    if (result.error) {
                        log('[' + ranking + ':p' + p + '] Error: ' + result.error);
                        errStreak++;
                        if (result.error === 403 || result.error === 429) {
                            log('Rate limited, waiting 60s...');
                            await delay(60000);
                        }
                        if (errStreak >= 3) { log('Too many errors, next ranking'); break; }
                        await delay(5000);
                        continue;
                    }

                    errStreak = 0;
                    const reviews = result.data?.reviews || [];
                    const apiTotalPages = result.data?.params?.totalPages || '?';

                    if (reviews.length === 0) { log('[' + ranking + ':p' + p + '] Empty, end'); break; }

                    let newCount = 0;
                    for (const r of reviews) {
                        if (r.reviewId && !seenIds.has(r.reviewId)) {
                            seenIds.add(r.reviewId);
                            allReviews.push(mapApiReview(r, allReviews.length));
                            newCount++;
                        }
                    }

                    log('[' + ranking + ':p' + p + '/' + apiTotalPages + '] +' + newCount + ' new → ' + allReviews.length + '/' + totalCount);

                    if (newCount === 0) {
                        dupeStreak++;
                        if (dupeStreak >= 3) { log('[' + ranking + '] API looping, next ranking'); break; }
                    } else {
                        dupeStreak = 0;
                    }

                    saveProgress(allReviews, false);
                    await delay(2000 + Math.random() * 4000);

                } catch(e) {
                    log('[' + ranking + ':p' + p + '] ' + e.message);
                    errStreak++;
                    if (errStreak >= 3) break;
                    await delay(5000);
                }
            }

            saveProgress(allReviews, false);
            log('[' + ranking + '] → ' + allReviews.length + '/' + totalCount);

            if (allReviews.length < totalCount) {
                const pause = 5000 + Math.random() * 10000;
                log('[pause] ' + Math.round(pause/1000) + 's...');
                await delay(pause);
            }
        }

        // ========================================
        // ШАГ 4: Scroll fallback
        // ========================================
        if (allReviews.length < totalCount) {
            log('\n=== STEP 4: Scroll fallback (need ' + (totalCount - allReviews.length) + ' more) ===');

            let scrollGotNew = false;
            const scrollListener = async (resp) => {
                if (!resp.url().includes('fetchReviews')) return;
                try {
                    const json = JSON.parse(await resp.text());
                    for (const r of (json.data?.reviews || [])) {
                        if (r.reviewId && !seenIds.has(r.reviewId)) {
                            seenIds.add(r.reviewId);
                            allReviews.push(mapApiReview(r, allReviews.length));
                            scrollGotNew = true;
                        }
                    }
                } catch(e) {}
            };
            page.on('response', scrollListener);

            async function scrollLoop(label) {
                const cp = await page.evaluate(() => {
                    const c = document.querySelector('.scroll__container');
                    if (c) { const r = c.getBoundingClientRect(); return { x: r.x+r.width/2, y: r.y+r.height/2 }; }
                    return null;
                });
                if (!cp) { log(label + ': no container'); return; }
                await page.mouse.move(cp.x, cp.y);

                let noNew = 0;
                for (let i = 0; i < 2000 && noNew < 30; i++) {
                    const before = allReviews.length;
                    scrollGotNew = false;
                    await page.mouse.wheel({ deltaY: 3000 });
                    let w = 0;
                    while (!scrollGotNew && w < 5000) { await delay(200); w += 200; }
                    if (allReviews.length > before) {
                        noNew = 0;
                        if (i % 15 === 0) log(label + ': ' + allReviews.length + '/' + totalCount);
                    } else { noNew++; }
                    if (allReviews.length >= totalCount) break;
                }
                log(label + ': ' + allReviews.length + '/' + totalCount);
            }

            const scrollSorts = [
                { label: 'default', ui: null },
                { label: 'by_time', ui: 'новизн' },
                { label: 'negative', ui: 'отрицательн' },
                { label: 'positive', ui: 'положительн' }
            ];

            for (const s of scrollSorts) {
                if (allReviews.length >= totalCount) break;
                log('\n--- Scroll: ' + s.label + ' ---');
                const ok = await safeGoto(reviewsUrl, 2);
                if (!ok) continue;
                if (s.ui) {
                    const sw = await switchSortUI(s.ui);
                    if (!sw) continue;
                }
                await scrollLoop(s.label);
                saveProgress(allReviews, false);
            }

            page.off('response', scrollListener);
        }

        // === ФИНАЛ ===
        const isComplete = allReviews.length >= totalCount;
        saveProgress(allReviews, true);

        log('\n========================================');
        log('RESULT: ' + allReviews.length + '/' + totalCount + ' reviews');
        log('Pages: ' + currentPageNum + ', Complete: ' + isComplete);
        log('========================================');

        console.log(JSON.stringify({
            organization: orgData,
            reviews: allReviews,
            total_pages: Math.ceil(allReviews.length / PAGE_SIZE)
        }));

    } catch (error) {
        console.error(JSON.stringify({ error: error.message }));
        process.exit(1);
    } finally {
        if (browser) { try { await browser.close(); } catch(e) {} }
        try { fs.rmSync(profileDir, { recursive: true, force: true }); } catch(e) {}
    }
})();