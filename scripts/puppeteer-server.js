#!/usr/bin/env node

import puppeteer from 'puppeteer';
import http from 'http';
import { URL } from 'url';

const PORT = process.env.PUPPETEER_PORT || 3000;

const executablePath = process.env.PUPPETEER_EXECUTABLE_PATH || '/usr/bin/chromium-browser';

async function scrapeReviews(url) {
    let browser;
    try {
        browser = await puppeteer.launch({
            headless: true,
            executablePath: executablePath,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-accelerated-2d-canvas',
                '--disable-gpu',
                '--disable-software-rasterizer',
                '--disable-extensions',
            ],
        });

        const page = await browser.newPage();
        
        await page.setViewport({ width: 1920, height: 1080 });
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        
        console.log(`Navigating to: ${url}`);
        await page.goto(url, {
            waitUntil: 'networkidle2',
            timeout: 60000,
        });

        console.log('Waiting for reviews to load...');
        try {
            await page.waitForSelector('[class*="review"], [class*="business-review"], [data-review-id]', {
                timeout: 30000,
            });
        } catch (e) {
            console.log('Review selector not found, waiting for page load...');
            await page.waitForTimeout(5000);
        }

        await page.evaluate(() => {
            window.scrollTo(0, document.body.scrollHeight);
        });
        await page.waitForTimeout(2000);

        const reviews = await page.evaluate(() => {
            const results = [];
            
            const selectors = [
                '[class*="review"]',
                '[class*="business-review"]',
                '[data-review-id]',
                'article[class*="review"]',
                'li[class*="review"]',
            ];

            let reviewElements = [];
            for (const selector of selectors) {
                reviewElements = Array.from(document.querySelectorAll(selector));
                if (reviewElements.length > 0) {
                    console.log(`Found ${reviewElements.length} reviews with selector: ${selector}`);
                    break;
                }
            }

            reviewElements.forEach((element, index) => {
                try {
                    const textSelectors = [
                        '[class*="review-text"]',
                        '[class*="text"]',
                        '[class*="comment"]',
                        'p',
                        'div[class*="content"]',
                    ];
                    
                    let text = '';
                    for (const sel of textSelectors) {
                        const textEl = element.querySelector(sel);
                        if (textEl) {
                            text = textEl.innerText || textEl.textContent || '';
                            if (text.length > 20) break;
                        }
                    }
                    
                    if (!text || text.length < 20) {
                        text = element.innerText || element.textContent || '';
                    }

                    let rating = 5;
                    const ratingSelectors = [
                        '[class*="rating"]',
                        '[class*="star"]',
                        '[data-rating]',
                        '[itemprop="ratingValue"]',
                    ];
                    
                    for (const sel of ratingSelectors) {
                        const ratingEl = element.querySelector(sel);
                        if (ratingEl) {
                            const ratingText = ratingEl.getAttribute('data-rating') 
                                || ratingEl.getAttribute('content')
                                || ratingEl.innerText
                                || '';
                            
                            const stars = element.querySelectorAll('[class*="star"][class*="filled"], [class*="star"].active, svg[class*="star"]');
                            if (stars.length > 0) {
                                rating = stars.length;
                            } else if (ratingText) {
                                const match = ratingText.match(/(\d+)/);
                                if (match) rating = parseInt(match[1]);
                            }
                            if (rating > 0 && rating <= 5) break;
                        }
                    }

                    let date = new Date().toISOString();
                    const dateSelectors = [
                        'time',
                        '[class*="date"]',
                        '[datetime]',
                        '[itemprop="datePublished"]',
                    ];
                    
                    for (const sel of dateSelectors) {
                        const dateEl = element.querySelector(sel);
                        if (dateEl) {
                            const dateText = dateEl.getAttribute('datetime') 
                                || dateEl.getAttribute('content')
                                || dateEl.innerText
                                || '';
                            if (dateText) {
                                const parsed = new Date(dateText);
                                if (!isNaN(parsed.getTime())) {
                                    date = parsed.toISOString();
                                    break;
                                }
                            }
                        }
                    }

                    let authorName = '';
                    const authorSelectors = [
                        '[class*="author"]',
                        '[class*="reviewer"]',
                        '[class*="user"]',
                        'a[href*="user"]',
                        '[itemprop="author"]',
                    ];
                    
                    for (const sel of authorSelectors) {
                        const authorEl = element.querySelector(sel);
                        if (authorEl) {
                            authorName = (authorEl.innerText || authorEl.textContent || '').trim();
                            authorName = authorName.replace(/^(Автор|Author|От|From):\s*/i, '');
                            if (authorName.length > 1 && authorName !== 'Аноним') break;
                        }
                    }

                    let phone = null;
                    const phoneEl = element.querySelector('[href^="tel:"]');
                    if (phoneEl) {
                        phone = phoneEl.getAttribute('href').replace('tel:', '');
                    } else {
                        const phoneMatch = element.innerText.match(/(\+?\d[\d\s\-\(\)]{7,})/);
                        if (phoneMatch) phone = phoneMatch[1];
                    }

                    let branch = 'Филиал 1';
                    const branchEl = element.querySelector('[class*="branch"], [class*="филиал"]');
                    if (branchEl) {
                        branch = (branchEl.innerText || branchEl.textContent || '').trim();
                    }

                    if (text && text.length >= 10) {
                        results.push({
                            text: text.trim(),
                            rating: rating || 5,
                            date: date,
                            reviewer_name: authorName || 'Аноним',
                            reviewer_phone: phone,
                            branch: branch,
                            external_id: `review_${index}_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`,
                        });
                    }
                } catch (e) {
                    console.error(`Error parsing review ${index}:`, e.message);
                }
            });

            return results;
        });

        await browser.close();
        return reviews;
    } catch (error) {
        if (browser) {
            await browser.close();
        }
        throw error;
    }
}

const server = http.createServer(async (req, res) => {
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
    res.setHeader('Content-Type', 'application/json');

    if (req.method === 'OPTIONS') {
        res.writeHead(200);
        res.end();
        return;
    }

    if (req.method !== 'GET' && req.method !== 'POST') {
        res.writeHead(405);
        res.end(JSON.stringify({ error: 'Method not allowed' }));
        return;
    }

    try {
        const url = new URL(req.url, `http://${req.headers.host}`);
        const targetUrl = url.searchParams.get('url') || (req.method === 'POST' ? await getPostData(req) : null);

        if (!targetUrl) {
            res.writeHead(400);
            res.end(JSON.stringify({ error: 'URL parameter is required' }));
            return;
        }

        console.log(`Scraping: ${targetUrl}`);
        const reviews = await scrapeReviews(targetUrl);
        
        res.writeHead(200);
        res.end(JSON.stringify({ success: true, reviews: reviews, count: reviews.length }));
    } catch (error) {
        console.error('Error:', error);
        res.writeHead(500);
        res.end(JSON.stringify({ error: error.message, success: false }));
    }
});

function getPostData(req) {
    return new Promise((resolve, reject) => {
        let body = '';
        req.on('data', chunk => {
            body += chunk.toString();
        });
        req.on('end', () => {
            try {
                const data = JSON.parse(body);
                resolve(data.url);
            } catch (e) {
                resolve(null);
            }
        });
        req.on('error', reject);
    });
}

server.listen(PORT, '0.0.0.0', () => {
    console.log(`Puppeteer server running on port ${PORT}`);
});
