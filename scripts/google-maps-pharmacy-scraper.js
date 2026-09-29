#!/usr/bin/env node
/**
 * Google Maps Pharmacy Scraper using Playwright
 * 
 * Usage: node scripts/google-maps-pharmacy-scraper.js <city> <state> [limit]
 * 
 * When limit is omitted or '0', scraping continues until results are exhausted.
 * Outputs ONLY valid JSON to stdout.
 * Diagnostics and logs are written to stderr.
 */

import { chromium } from 'playwright';

const SCRAPER_VERSION = "pharmacy-worldwide-v2";

const rawCity = process.argv[2] ? process.argv[2].trim() : 'Boston';
const rawState = process.argv[3] ? process.argv[3].trim() : 'MA';
const rawLimit = process.argv[4] ? process.argv[4].trim() : null;

const US_STATES = {
    'alabama': 'AL', 'alaska': 'AK', 'arizona': 'AZ', 'arkansas': 'AR', 'california': 'CA',
    'colorado': 'CO', 'connecticut': 'CT', 'delaware': 'DE', 'florida': 'FL', 'georgia': 'GA',
    'hawaii': 'HI', 'idaho': 'ID', 'illinois': 'IL', 'indiana': 'IN', 'iowa': 'IA',
    'kansas': 'KS', 'kentucky': 'KY', 'louisiana': 'LA', 'maine': 'ME', 'maryland': 'MD',
    'massachusetts': 'MA', 'michigan': 'MI', 'minnesota': 'MN', 'mississippi': 'MS', 'missouri': 'MO',
    'montana': 'MT', 'nebraska': 'NE', 'nevada': 'NV', 'new hampshire': 'NH', 'new jersey': 'NJ',
    'new mexico': 'NM', 'new york': 'NY', 'north carolina': 'NC', 'north dakota': 'ND', 'ohio': 'OH',
    'oklahoma': 'OK', 'oregon': 'OR', 'pennsylvania': 'PA', 'rhode island': 'RI', 'south carolina': 'SC',
    'south dakota': 'SD', 'tennessee': 'TN', 'texas': 'TX', 'utah': 'UT', 'vermont': 'VT',
    'virginia': 'VA', 'washington': 'WA', 'west virginia': 'WV', 'wisconsin': 'WI', 'wyoming': 'WY',
    'district of columbia': 'DC'
};

function normalizeStateCode(s) {
    if (!s) return s;
    const lower = s.toLowerCase().trim();
    return US_STATES[lower] || s.trim();
}

const city = rawCity;
const state = normalizeStateCode(rawState);

// Determine if there is an explicit positive limit or unlimited
let limit = null;
if (rawLimit && rawLimit !== '0' && rawLimit.toLowerCase() !== 'unlimited' && rawLimit.toLowerCase() !== 'null') {
    const parsed = parseInt(rawLimit, 10);
    if (!isNaN(parsed) && parsed > 0) {
        limit = parsed;
    }
}
const isUnlimited = (limit === null);

const log = (...args) => console.error('[PharmacyScraper]', ...args);

function outputJson(data) {
    process.stdout.write(JSON.stringify(data));
}

function isValidBusinessName(name, targetCity = '', targetState = '') {
    if (!name || typeof name !== 'string') return false;
    const clean = name.trim();
    if (clean.length < 2 || clean.length > 120) return false;

    // Reject Google internal tokens, hashes and IDs
    if (/^(0ahUKE|0x|ChIJ|CAE)/i.test(clean)) return false;
    if (/^[A-Za-z0-9_-]{18,}$/.test(clean)) return false;

    // Reject standard UI action labels
    const forbiddenLabels = [
        'directions', 'website', 'call', 'share', 'save', 'results',
        'sponsored', 'send to phone', 'nearby', 'menu', 'overview',
        'reviews', 'about', 'photos', 'claim this business', 'suggest an edit',
        'closed', 'open', 'open 24 hours', 'temporarily closed', 'book online'
    ];
    if (forbiddenLabels.includes(clean.toLowerCase())) return false;

    // Reject raw coordinates or URLs
    if (/^[-+]?\d{1,3}\.\d+,\s*[-+]?\d{1,3}\.\d+$/.test(clean)) return false;
    if (/^https?:\/\//i.test(clean)) return false;

    // Reject city/state boundary labels (e.g. "Boston, MA, USA")
    const lower = clean.toLowerCase();
    if (targetCity) {
        const cLower = targetCity.toLowerCase();
        const sLower = targetState ? targetState.toLowerCase() : '';
        if (lower === cLower || lower === `${cLower}, ${sLower}` || lower === `${cLower}, ${sLower}, usa` || lower === `${cLower}, usa`) {
            return false;
        }
    }

    // Must contain at least one letter
    return /[a-zA-Z]/.test(clean);
}

const EXCLUDED_PHARMACY_NAME_PATTERNS = [
    'storage', 'self storage', 'public storage', 'extra space', 'cubesmart', 'life storage',
    'the ups store', 'ups store', 'fedex', 'usps', 'post office', 'postal service', 'shipping',
    'photo', 'print shop', 'printing',
    'dollar general', 'dollar tree', 'family dollar', '99 cents', 'five below',
    'ulta beauty', 'sephora', 'sally beauty', 'cosmetics', 'beauty supply', 'nail salon', 'hair salon', 'barber',
    'dispensary', 'cannabis', 'marijuana', 'weed', 'cbd store', 'smoke shop', 'vape shop', 'hookah',
    'cumberland farms', '7-eleven', 'circle k', 'wawa', 'sheetz', 'speedway', 'gas station', 'convenience store', 'pick n pay',
    'minuteclinic', 'urgent care', 'covid-19 drive-thru', 'covid-19 testing', 'testing site',
    'optometry', 'eyecare', 'vision center', 'dentist', 'dental', 'veterinary', 'animal hospital'
];

const EXCLUDED_PHARMACY_CATEGORIES = [
    'self-storage', 'storage facility', 'shipping service', 'photo shop', 'photo lab', 'dollar store',
    'cosmetics store', 'cannabis store', 'dispensary', 'convenience store', 'gas station', 'urgent care',
    'medical clinic', 'walk-in clinic', 'supermarket', 'department store', 'grocery store', 'optometrist', 'dentist'
];

function hasPharmacyKeyword(text) {
    if (!text || typeof text !== 'string') return false;
    const lower = text.toLowerCase();
    return lower.includes('pharmacy') ||
        lower.includes('pharmacies') ||
        lower.includes('drug store') ||
        lower.includes('drugstore') ||
        lower.includes('chemist') ||
        lower.includes('apothecary') ||
        lower.includes('medical store') ||
        lower.includes('farmacia') ||
        lower.includes('prescription') ||
        lower.includes(' rx') ||
        lower.endsWith('rx');
}

function isKnownPharmacyChain(name) {
    if (!name || typeof name !== 'string') return false;
    const lower = name.toLowerCase();
    const chains = [
        'cvs', 'walgreens', 'rite aid', 'boots', 'duane reade', 'fazal din', 'servaid',
        'clinix', 'dvago', 'apollo pharmacy', 'medplus', 'guardian pharmacy', 'chemist warehouse'
    ];
    return chains.some(c => lower.includes(c));
}

function isStrictPharmacy(category, name) {
    if (!name || typeof name !== 'string') return false;
    const nLower = name.toLowerCase().trim();
    const catLower = (category || '').toLowerCase().trim();

    // 1. Reject if name matches explicit non-pharmacy business patterns
    for (const pattern of EXCLUDED_PHARMACY_NAME_PATTERNS) {
        if (nLower.includes(pattern)) {
            return false;
        }
    }

    // 2. Reject if category is explicitly excluded and name lacks a pharmacy keyword
    for (const exc of EXCLUDED_PHARMACY_CATEGORIES) {
        if (catLower.includes(exc) && !hasPharmacyKeyword(nLower)) {
            return false;
        }
    }

    // 3. Accept known major standalone pharmacy chains
    if (isKnownPharmacyChain(nLower)) {
        return true;
    }

    // 4. Accept if name or category explicitly identifies as a pharmacy/chemist/drug store
    if (hasPharmacyKeyword(nLower) || hasPharmacyKeyword(catLower)) {
        return true;
    }

    return false;
}

function normalizeBrandName(name) {
    if (!name) return '';
    let clean = name.toLowerCase();
    clean = clean.replace(/\b(pharmacy|pharmacies|drug\s*store|drugstore|chemist|apothecary|medical\s*store|rx|store|supercenter|market|supermarket)\b/gi, '');
    clean = clean.replace(/#\d+/g, '');
    clean = clean.replace(/\bste\s*\d+\b/gi, '');
    return clean.replace(/[^a-z0-9]/g, '');
}

function normalizeStreet(street) {
    if (!street) return '';
    let s = street.toLowerCase();
    s = s.replace(/\b(street|st\.)\b/g, 'st');
    s = s.replace(/\b(avenue|ave\.)\b/g, 'ave');
    s = s.replace(/\b(road|rd\.)\b/g, 'rd');
    s = s.replace(/\b(boulevard|blvd\.)\b/g, 'blvd');
    s = s.replace(/\b(drive|dr\.)\b/g, 'dr');
    return s.replace(/[^a-z0-9]/g, '');
}


function extractCoordinatesAndPlaceId(url) {
    let latitude = null;
    let longitude = null;
    let googlePlaceId = null;

    if (!url) return { latitude, longitude, googlePlaceId };

    // Format 1: !3d42.3511928!4d-71.0724247
    const latMatch = url.match(/!3d([0-9.-]+)/);
    const lngMatch = url.match(/!4d([0-9.-]+)/);
    if (latMatch && lngMatch) {
        latitude = parseFloat(latMatch[1]);
        longitude = parseFloat(lngMatch[1]);
    } else {
        // Format 2: @42.3511928,-71.0724247
        const atMatch = url.match(/@([0-9.-]+),([0-9.-]+)/);
        if (atMatch) {
            latitude = parseFloat(atMatch[1]);
            longitude = parseFloat(atMatch[2]);
        }
    }

    // Extract ChIJ... place ID from !19sChIJ...
    const placeIdMatch = url.match(/!19s(ChIJ[A-Za-z0-9_-]+)/);
    if (placeIdMatch) {
        googlePlaceId = placeIdMatch[1];
    }

    return { latitude, longitude, googlePlaceId };
}

function extractPhoneNumber(rawText, dataItemId = null) {
    if (dataItemId && dataItemId.toLowerCase().startsWith('phone:tel:')) {
        const tel = dataItemId.substring(10).trim();
        if (tel.length >= 7) return tel;
    }
    if (!rawText) return null;
    let clean = rawText.replace(/^(?:Phone|Call|Telephone):\s*/i, '').trim();
    const match = clean.match(/(?:\+?\d{1,4}[-.\s]?)?(?:\(?\d{1,5}\)?[-.\s]?)?\d{3,4}[-.\s]?\d{3,4}(?:[-.\s]?\d{1,4})?/);
    if (match && match[0].replace(/\D/g, '').length >= 7) {
        return match[0].trim();
    }
    const digits = clean.replace(/\D/g, '');
    if (digits.length >= 7 && digits.length <= 15) {
        return clean;
    }
    return null;
}

const KNOWN_COUNTRIES = new Set([
    'pakistan', 'united states', 'usa', 'u.s.a.', 'united kingdom', 'uk', 'u.k.',
    'canada', 'australia', 'india', 'united arab emirates', 'uae', 'saudi arabia',
    'germany', 'france', 'italy', 'spain', 'brazil', 'mexico', 'japan', 'china',
    'bangladesh', 'sri lanka', 'nepal', 'south africa', 'new zealand', 'ireland',
    'netherlands', 'switzerland', 'sweden', 'norway', 'denmark', 'singapore',
    'malaysia', 'philippines', 'indonesia', 'thailand', 'vietnam', 'egypt', 'turkey'
]);

function isCountryToken(token) {
    if (!token) return false;
    const clean = token.toLowerCase().replace(/[^a-z\s.]/g, '').trim();
    return KNOWN_COUNTRIES.has(clean);
}

function parseAddressString(addressStr, defaultCity, defaultState) {
    if (!addressStr || typeof addressStr !== 'string') {
        return { street_address: null, city: defaultCity, state: defaultState, postal_code: null };
    }

    // 1. Remove leading prefixes like "Address: "
    let clean = addressStr.replace(/^Address:\s*/i, '').trim();

    // 2. Split text by comma
    let parts = clean.split(',').map(s => s.trim()).filter(Boolean);

    // 3. Remove trailing country if present (e.g. "Pakistan", "United States", "USA", etc.)
    if (parts.length > 1 && isCountryToken(parts[parts.length - 1])) {
        parts.pop();
    }

    let postalCode = null;
    let stateVal = defaultState;
    let cityVal = defaultCity;
    let streetVal = null;

    if (parts.length === 0) {
        return { street_address: null, city: defaultCity, state: defaultState, postal_code: null };
    }

    if (parts.length === 1) {
        if (defaultCity && parts[0].toLowerCase() === defaultCity.toLowerCase()) {
            return { street_address: null, city: defaultCity, state: defaultState, postal_code: null };
        }
        return { street_address: parts[0], city: defaultCity, state: defaultState, postal_code: null };
    }

    // 4. Look for an exact or substring match of defaultCity in the parts
    let cityIndex = -1;
    if (defaultCity) {
        const cLower = defaultCity.toLowerCase();
        for (let i = parts.length - 1; i >= 0; i--) {
            const pLower = parts[i].toLowerCase();
            if (pLower === cLower || pLower.startsWith(cLower + ' ') || pLower.endsWith(' ' + cLower)) {
                cityIndex = i;
                break;
            }
        }
    }

    if (cityIndex >= 0) {
        // City found at cityIndex
        cityVal = parts[cityIndex] || defaultCity;

        // Street is everything before cityIndex
        if (cityIndex > 0) {
            streetVal = parts.slice(0, cityIndex).join(', ');
        }

        // State & postal code are in segments after cityIndex
        const afterParts = parts.slice(cityIndex + 1);
        for (const after of afterParts) {
            // Check for US state + zip: "MA 02111"
            const stateZipMatch = after.match(/\b([A-Za-z]{2})\s+(\d{5}(?:-\d{4})?)\b/);
            if (stateZipMatch) {
                stateVal = stateZipMatch[1].toUpperCase();
                postalCode = String(stateZipMatch[2]);
                continue;
            }

            // Check for standalone 5-digit postal code (e.g. "54000", "02111")
            const zipMatch = after.match(/\b(\d{5}(?:-\d{4})?)\b/);
            if (zipMatch) {
                postalCode = String(zipMatch[1]);
                const remainingAfter = after.replace(zipMatch[0], '').replace(/,\s*$/, '').trim();
                if (remainingAfter && !isCountryToken(remainingAfter)) {
                    stateVal = remainingAfter;
                }
                continue;
            }

            // If not a country token and looks like a state/province name
            if (!isCountryToken(after) && /[a-zA-Z]/.test(after)) {
                stateVal = after;
            }
        }
    } else {
        // Fallback: defaultCity not explicitly found in parts.
        // Inspect from right side backwards
        let lastPart = parts[parts.length - 1];

        // Check if last part contains postal code / state
        const stateZipMatch = lastPart.match(/\b([A-Za-z]{2})\s+(\d{5}(?:-\d{4})?)\b/);
        if (stateZipMatch) {
            stateVal = stateZipMatch[1].toUpperCase();
            postalCode = String(stateZipMatch[2]);
            parts.pop();
        } else {
            const zipMatch = lastPart.match(/\b(\d{5}(?:-\d{4})?)\b/);
            if (zipMatch) {
                postalCode = String(zipMatch[1]);
                const rem = lastPart.replace(zipMatch[0], '').trim();
                if (rem && !isCountryToken(rem)) {
                    stateVal = rem;
                }
                parts.pop();
            } else if (defaultState && lastPart.toLowerCase() === defaultState.toLowerCase()) {
                stateVal = lastPart;
                parts.pop();
            }
        }

        if (parts.length >= 2) {
            cityVal = parts[parts.length - 1] || defaultCity;
            streetVal = parts.slice(0, parts.length - 1).join(', ');
        } else if (parts.length === 1) {
            streetVal = parts[0];
        }
    }

    if (streetVal && defaultCity && streetVal.toLowerCase() === defaultCity.toLowerCase()) {
        streetVal = null;
    }

    return {
        street_address: streetVal || null,
        city: cityVal || defaultCity,
        state: stateVal || defaultState,
        postal_code: postalCode ? String(postalCode) : null,
    };
}

async function detectPageMode(page) {
    const currentUrl = page.url();

    // 1. Google verification / bot challenge
    if (currentUrl.includes('/sorry/')) {
        return 'blocked';
    }
    const content = await page.content();
    if (content.includes('unusual traffic') || content.includes('recaptcha') || content.includes('enablejs')) {
        return 'blocked';
    }

    // 2. Direct Single-Place Mode by URL
    if (currentUrl.includes('/maps/place/')) {
        return 'single_place';
    }

    // 3. Multi-result Feed Mode
    const feed = await page.$('div[role="feed"]');
    const cards = await page.$$('div[role="article"], div.Nv2PK');
    if (feed || cards.length > 0) {
        return 'feed';
    }

    // 4. Single-Place by DOM elements (when no feed exists)
    const singlePlaceHeading = await page.$('h1.DUwDvf, div[role="main"] h1, [role="main"] h1, div.TIHn2 h1, h1');
    if (singlePlaceHeading) {
        const h1Text = (await singlePlaceHeading.innerText()).trim();
        if (h1Text && h1Text.toLowerCase() !== 'results' && !h1Text.toLowerCase().includes('results for')) {
            const hasActionOrAddress = await page.$('button[data-item-id="address"], button[aria-label*="Address" i], button[data-item-id^="phone:"], button[aria-label*="Phone" i], button[jsaction*="category"], a[data-item-id="authority"]');
            if (hasActionOrAddress) {
                return 'single_place';
            }
        }
    }

    // 5. No Results
    const noResults = await page.$('div.Q2vBSc, p.fontBodyMedium:has-text("can\'t find"), span:has-text("No results found"), p:has-text("can\'t find")');
    if (noResults && (await noResults.isVisible())) {
        return 'no_results';
    }

    return 'unknown';
}

async function extractSinglePlace(page, targetCity, targetState) {
    try {
        let name = null;
        const nameEl = await page.$('h1.DUwDvf, div[role="main"] h1, [role="main"] h1, div.TIHn2 h1, h1');
        if (nameEl) {
            name = (await nameEl.innerText()).trim();
        }

        if (!name || !isValidBusinessName(name, targetCity, targetState)) {
            return null;
        }

        // Extract category
        let category = null;
        const catBtn = await page.$('button[jsaction*="category"], div.fontBodyMedium button, span.W4P4ne, button.DkEaL');
        if (catBtn) {
            category = (await catBtn.innerText()).trim();
        }

        if (!isStrictPharmacy(category, name)) {
            log(`[SinglePlace Rejected - Non-Pharmacy] ${name} (category: "${category || 'None'}")`);
            return null;
        }

        // Extract address
        let fullAddressText = null;
        const addrBtn = await page.$('button[data-item-id="address"], button[aria-label*="Address" i], [data-item-id="address"]');
        if (addrBtn) {
            const aria = await addrBtn.getAttribute('aria-label');
            const inner = await addrBtn.innerText();
            fullAddressText = (aria && aria.trim()) || (inner && inner.replace(/\n/g, ', ').trim()) || null;
        }

        // Extract phone
        let phone = null;
        const phoneBtn = await page.$('button[data-item-id^="phone:"], button[aria-label*="Phone" i]');
        if (phoneBtn) {
            const pAria = await phoneBtn.getAttribute('aria-label');
            const pInner = await phoneBtn.innerText();
            const pDataId = await phoneBtn.getAttribute('data-item-id');
            phone = extractPhoneNumber(pAria || pInner, pDataId);
        }

        // Extract website
        let website = null;
        const webBtn = await page.$('a[data-item-id="authority"], a[aria-label*="website" i]');
        if (webBtn) {
            website = await webBtn.getAttribute('href');
        }

        // Extract coordinates & Place ID
        const urlMeta = extractCoordinatesAndPlaceId(page.url());
        const parsed = parseAddressString(fullAddressText, targetCity, targetState);

        return {
            name,
            street_address: parsed.street_address,
            city: parsed.city,
            state: parsed.state,
            postal_code: parsed.postal_code,
            phone: phone,
            website: website,
            latitude: urlMeta.latitude,
            longitude: urlMeta.longitude,
            google_place_id: urlMeta.googlePlaceId,
            external_source_url: page.url() || null,
            isComplete: true,
        };
    } catch (e) {
        log('Error extracting single place:', e.message);
        return null;
    }
}

async function run() {
    log(`Launching browser for pharmacy search in "${city}, ${state}" (${isUnlimited ? 'unlimited' : 'limit: ' + limit}) [Version: ${SCRAPER_VERSION}]`);

    // Smart region-aware query formulation for pharmacies
    const isUS = Boolean(US_STATES[rawState.toLowerCase().trim()] || (rawState.length === 2 && /^[A-Za-z]{2}$/.test(rawState)));
    const isSouthAsia = /^(punjab|sindh|kpk|khyber|balochistan|islamabad|lahore|karachi|rawalpindi|faisalabad|multan|peshawar|quetta|delhi|mumbai|bangalore|hyderabad|chennai|kolkata|dhaka)/i.test(rawState) ||
                        /^(punjab|sindh|kpk|khyber|balochistan|islamabad|lahore|karachi|rawalpindi|faisalabad|multan|peshawar|quetta|delhi|mumbai|bangalore|hyderabad|chennai|kolkata|dhaka)/i.test(rawCity);

    let searchQueries = [
        `pharmacy ${city} ${state}`,
        `pharmacies in ${city}, ${state}`
    ];

    if (isUS) {
        searchQueries.push(`CVS Pharmacy ${city} ${state}`);
        searchQueries.push(`Walgreens ${city} ${state}`);
        searchQueries.push(`drug store ${city} ${state}`);
    } else if (isSouthAsia) {
        searchQueries.push(`medical store ${city} ${state}`);
        searchQueries.push(`chemist ${city} ${state}`);
    } else {
        searchQueries.push(`chemist ${city} ${state}`);
        searchQueries.push(`drug store ${city} ${state}`);
    }

    let browser;
    try {
        browser = await chromium.launch({
            headless: true,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-blink-features=AutomationControlled',
            ],
        });

        const context = await browser.newContext({
            userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            locale: 'en-US',
            viewport: { width: 1280, height: 900 },
        });

        const page = await context.newPage();
        const candidateMap = new Map();
        const queryDiagnostics = [];
        let totalRawCandidates = 0;
        let totalDuplicates = 0;
        let overallStopReason = 'end_of_results_reached';

        const feedSelector = 'div[role="feed"]';
        const cardSelector = 'div[role="article"], div[role="feed"] > div > div[jsaction], div.Nv2PK';

        for (let qIdx = 0; qIdx < searchQueries.length; qIdx++) {
            if (!isUnlimited && candidateMap.size >= limit) {
                overallStopReason = 'requested_limit_reached';
                break;
            }

            const currentQuery = searchQueries[qIdx];
            const searchUrl = `https://www.google.com/maps/search/${encodeURIComponent(currentQuery)}?hl=en`;
            log(`[Query ${qIdx + 1}/${searchQueries.length}] Navigating to ${searchUrl}`);

            try {
                await page.goto(searchUrl, { waitUntil: 'domcontentloaded', timeout: 30000 });
            } catch (navErr) {
                log(`Navigation error for query "${currentQuery}":`, navErr.message);
                queryDiagnostics.push({ query: currentQuery, page_mode: 'error', error: navErr.message, final_url: page.url() });
                continue;
            }

            // Dismiss consent if present
            try {
                const consentBtn = page.locator('form[action*="consent"] button, button[aria-label*="Accept all" i], button:has-text("Accept all"), button:has-text("I agree")').first();
                if (await consentBtn.isVisible({ timeout: 2000 })) {
                    log('Dismissing consent dialog...');
                    await consentBtn.click();
                    await page.waitForTimeout(1000);
                }
            } catch (e) { }

            // Allow initial render
            try {
                await page.waitForSelector(`${feedSelector}, ${cardSelector}, h1.DUwDvf, [role="main"] h1, div.Q2vBSc`, { timeout: 6000 });
            } catch (e) { }

            const pageMode = await detectPageMode(page);
            const finalUrl = page.url();
            log(`[Query ${qIdx + 1}] Detected page mode: "${pageMode}"`);

            if (pageMode === 'blocked') {
                log('Google Maps verification challenge detected.');
                outputJson({
                    status: 'blocked',
                    results: Array.from(candidateMap.values()),
                    stop_reason: 'google_challenge_blocked',
                    message: 'Google Maps returned a verification challenge or bot detection page.',
                    meta: {
                        scraper_version: SCRAPER_VERSION,
                        type: 'pharmacies',
                        requested_limit: limit,
                        is_unlimited: isUnlimited,
                        raw_candidates: totalRawCandidates,
                        accepted_candidates: candidateMap.size,
                        duplicates_removed: totalDuplicates,
                        final_unique: candidateMap.size,
                        stop_reason: 'google_challenge_blocked',
                        city: city,
                        state: state,
                        queries: queryDiagnostics
                    }
                });
                await browser.close();
                return;
            }

            if (pageMode === 'single_place') {
                totalRawCandidates++;
                const singlePlace = await extractSinglePlace(page, city, state);
                if (singlePlace) {
                    const key = singlePlace.google_place_id
                        ? `pid:${singlePlace.google_place_id}`
                        : `${singlePlace.name.toLowerCase()}|${(singlePlace.street_address || singlePlace.city || '').toLowerCase()}`;

                    if (!candidateMap.has(key)) {
                        candidateMap.set(key, singlePlace);
                        log(`[SinglePlace Accepted] ${singlePlace.name} -> ${singlePlace.street_address || 'No street'}, ${singlePlace.city}, ${singlePlace.state}`);
                        queryDiagnostics.push({
                            query: currentQuery,
                            final_url: finalUrl,
                            page_mode: 'single_place',
                            feed_found: false,
                            place_heading_found: true,
                            place_name: singlePlace.name,
                            raw_candidates: 1,
                            accepted_candidates: 1,
                            rejected_candidates: 0,
                            rejection_reason: null
                        });
                    } else {
                        totalDuplicates++;
                        log(`[SinglePlace Duplicate] Already collected: ${singlePlace.name}`);
                        queryDiagnostics.push({
                            query: currentQuery,
                            final_url: finalUrl,
                            page_mode: 'single_place',
                            feed_found: false,
                            place_heading_found: true,
                            place_name: singlePlace.name,
                            raw_candidates: 1,
                            accepted_candidates: 0,
                            rejected_candidates: 1,
                            rejection_reason: 'duplicate'
                        });
                    }
                } else {
                    queryDiagnostics.push({
                        query: currentQuery,
                        final_url: finalUrl,
                        page_mode: 'single_place',
                        feed_found: false,
                        place_heading_found: false,
                        place_name: null,
                        raw_candidates: 1,
                        accepted_candidates: 0,
                        rejected_candidates: 1,
                        rejection_reason: 'invalid_business_name'
                    });
                }
                continue;
            }

            if (pageMode === 'no_results') {
                log(`Query ${qIdx + 1} produced no results.`);
                queryDiagnostics.push({
                    query: currentQuery,
                    final_url: finalUrl,
                    page_mode: 'no_results',
                    feed_found: false,
                    place_heading_found: false,
                    place_name: null,
                    raw_candidates: 0,
                    accepted_candidates: 0,
                    rejected_candidates: 0,
                    rejection_reason: null
                });
                continue;
            }

            if (pageMode === 'unknown') {
                log(`Query ${qIdx + 1} produced unknown page layout.`);
                queryDiagnostics.push({
                    query: currentQuery,
                    final_url: finalUrl,
                    page_mode: 'unknown',
                    feed_found: false,
                    place_heading_found: false,
                    place_name: null,
                    raw_candidates: 0,
                    accepted_candidates: 0,
                    rejected_candidates: 0,
                    rejection_reason: 'unknown_page_layout'
                });
                continue;
            }

            // Multi-result Feed Mode
            let scrollAttempts = 0;
            const maxScrollAttempts = isUnlimited ? 25 : Math.max(10, Math.ceil((limit - candidateMap.size) * 2));
            let consecutiveNoNewCards = 0;
            let lastCandidateCount = candidateMap.size;
            let lastScrollHeight = 0;
            let queryRawCount = 0;
            let queryAcceptedCount = 0;
            let queryDuplicatesCount = 0;

            while (scrollAttempts < maxScrollAttempts) {
                scrollAttempts++;

                const cards = await page.$$(cardSelector);
                log(`Query ${qIdx + 1} - Iteration ${scrollAttempts}: ${cards.length} cards visible (unique total: ${candidateMap.size}${isUnlimited ? '' : '/' + limit})`);

                for (let i = 0; i < cards.length; i++) {
                    if (!isUnlimited && candidateMap.size >= limit) {
                        overallStopReason = 'requested_limit_reached';
                        break;
                    }
                    const card = cards[i];

                    try {
                        const linkEl = await card.$('a[href*="/maps/place/"], a.hfpxzc');
                        const href = linkEl ? await linkEl.getAttribute('href') : null;

                        let name = null;
                        const nameEl = await card.$('div.fontHeadlineSmall, div.qBF1Pd, [role="heading"]');
                        if (nameEl) {
                            name = (await nameEl.innerText()).trim();
                        } else if (linkEl) {
                            const aria = await linkEl.getAttribute('aria-label');
                            if (aria) name = aria.trim();
                        }

                        if (!isValidBusinessName(name, city, state)) {
                            continue;
                        }

                        const rawCardText = await card.innerText();
                        const tokens = rawCardText.split(/[\n·\u00b7]/).map(t => t.trim()).filter(Boolean);

                        let category = null;
                        const catEl = await card.$('span.W4P4ne, div.W4P4ne, span[class*="category" i]');
                        if (catEl) {
                            category = (await catEl.innerText()).trim();
                        }
                        if (!category && tokens.length > 1) {
                            for (const tok of tokens) {
                                if (EXCLUDED_PHARMACY_CATEGORIES.some(exc => tok.toLowerCase().includes(exc)) || hasPharmacyKeyword(tok)) {
                                    category = tok;
                                    break;
                                }
                            }
                        }

                        if (!isStrictPharmacy(category, name)) {
                            log(`[Card Rejected - Non-Pharmacy] ${name} (category: "${category || 'None'}")`);
                            continue;
                        }

                        queryRawCount++;
                        totalRawCandidates++;

                        const metaFromUrl = extractCoordinatesAndPlaceId(href);

                        let cardStreetAddress = null;
                        let cardPhone = null;

                        for (const token of tokens) {
                            if (!cardPhone) {
                                const phoneMatch = extractPhoneNumber(token);
                                if (phoneMatch) {
                                    cardPhone = phoneMatch;
                                    continue;
                                }
                            }

                            if (!cardStreetAddress) {
                                if (/^\d+\s+[A-Za-z0-9\s.,-]+\b(?:Street|St|Avenue|Ave|Road|Rd|Boulevard|Blvd|Drive|Dr|Way|Highway|Hwy|Parkway|Pkwy|Place|Pl|Court|Ct|Lane|Ln)\b/i.test(token)) {
                                    cardStreetAddress = token;
                                }
                            }
                        }

                        let cardWebsite = null;
                        const webEl = await card.$('a[data-value="Website"], a[aria-label*="website" i]');
                        if (webEl) {
                            cardWebsite = await webEl.getAttribute('href');
                        }

                        const normBrand = normalizeBrandName(name);
                        const normStreet = normalizeStreet(cardStreetAddress);

                        const key = (normStreet && normBrand)
                            ? `addr:${normStreet}|${normBrand}`
                            : (metaFromUrl.googlePlaceId ? `pid:${metaFromUrl.googlePlaceId}` : `${name.toLowerCase()}|${(cardStreetAddress || href || '').toLowerCase()}`);

                        if (!candidateMap.has(key)) {
                            queryAcceptedCount++;
                            candidateMap.set(key, {
                                name,
                                href,
                                category,
                                cardStreetAddress,
                                cardPhone,
                                cardWebsite,
                                latitude: metaFromUrl.latitude,
                                longitude: metaFromUrl.longitude,
                                google_place_id: metaFromUrl.googlePlaceId,
                                isComplete: false,
                            });
                        } else {
                            // If existing candidate has generic brand and this has specific "Pharmacy", upgrade the candidate name
                            const existing = candidateMap.get(key);
                            if (hasPharmacyKeyword(name) && !hasPharmacyKeyword(existing.name)) {
                                existing.name = name;
                            }
                            queryDuplicatesCount++;
                            totalDuplicates++;
                        }
                    } catch (cardErr) { }
                }

                if (!isUnlimited && candidateMap.size >= limit) {
                    overallStopReason = 'requested_limit_reached';
                    break;
                }

                // Check end of results indicator
                const endIndicator = await page.$('div.HlvSq, p.fontBodyMedium:has-text("You\'ve reached the end"), span:has-text("You\'ve reached the end")');
                if (endIndicator && (await endIndicator.isVisible())) {
                    log(`Query ${qIdx + 1} reached end of results.`);
                    break;
                }

                if (candidateMap.size === lastCandidateCount) {
                    consecutiveNoNewCards++;
                    if (consecutiveNoNewCards >= 4) {
                        log(`Query ${qIdx + 1} produced no new candidates after 4 scrolls.`);
                        break;
                    }
                } else {
                    consecutiveNoNewCards = 0;
                    lastCandidateCount = candidateMap.size;
                }

                const feed = await page.$(feedSelector);
                let currentScrollHeight = 0;
                if (feed) {
                    currentScrollHeight = await feed.evaluate(el => {
                        el.scrollBy(0, 2000);
                        return el.scrollHeight;
                    });
                } else {
                    currentScrollHeight = await page.evaluate(() => {
                        window.scrollBy(0, 1500);
                        return document.body.scrollHeight;
                    });
                }

                await page.waitForTimeout(1000);

                if (currentScrollHeight === lastScrollHeight && consecutiveNoNewCards >= 3) {
                    break;
                }
                lastScrollHeight = currentScrollHeight;
            }

            queryDiagnostics.push({
                query: currentQuery,
                final_url: finalUrl,
                page_mode: 'feed',
                feed_found: true,
                place_heading_found: false,
                place_name: null,
                raw_candidates: queryRawCount,
                accepted_candidates: queryAcceptedCount,
                rejected_candidates: queryDuplicatesCount,
                rejection_reason: queryDuplicatesCount > 0 ? 'duplicate' : null
            });
        }

        // Fetch full place details for candidates (only those not already complete)
        log(`Retrieving full place details for ${candidateMap.size} unique candidate pharmacies...`);
        const detailPage = await context.newPage();
        const finalResults = [];

        for (const candidate of candidateMap.values()) {
            if (!isUnlimited && finalResults.length >= limit) {
                break;
            }

            if (candidate.isComplete) {
                finalResults.push({
                    name: candidate.name,
                    street_address: candidate.street_address,
                    city: candidate.city,
                    state: candidate.state,
                    postal_code: candidate.postal_code,
                    phone: candidate.phone,
                    website: candidate.website,
                    latitude: candidate.latitude,
                    longitude: candidate.longitude,
                    google_place_id: candidate.google_place_id,
                    external_source_url: candidate.external_source_url || candidate.href || null,
                });
                log(`Accepted Direct Pharmacy [${finalResults.length}${isUnlimited ? '' : '/' + limit}]: ${candidate.name} -> ${candidate.street_address || 'No street'}, ${candidate.city}, ${candidate.state} ${candidate.postal_code || ''}`);
                continue;
            }

            let fullAddressText = null;
            let detailPhone = candidate.cardPhone;
            let detailWebsite = candidate.cardWebsite;
            let detailLat = candidate.latitude;
            let detailLng = candidate.longitude;
            let detailPlaceId = candidate.google_place_id;

            if (candidate.href) {
                try {
                    await detailPage.goto(candidate.href, { waitUntil: 'domcontentloaded', timeout: 10000 });

                    try {
                        await detailPage.waitForSelector('button[data-item-id="address"], button[aria-label*="Address" i], h1', { timeout: 2500 });
                    } catch (e) { }

                    const addrBtn = await detailPage.$('button[data-item-id="address"], button[aria-label*="Address" i], [data-item-id="address"]');
                    if (addrBtn) {
                        const aria = await addrBtn.getAttribute('aria-label');
                        const inner = await addrBtn.innerText();
                        fullAddressText = (aria && aria.trim()) || (inner && inner.replace(/\n/g, ', ').trim()) || null;
                    }

                    if (!detailPhone) {
                        const phoneBtn = await detailPage.$('button[data-item-id^="phone:"], button[aria-label*="Phone" i]');
                        if (phoneBtn) {
                            const pAria = await phoneBtn.getAttribute('aria-label');
                            const pInner = await phoneBtn.innerText();
                            const pDataId = await phoneBtn.getAttribute('data-item-id');
                            detailPhone = extractPhoneNumber(pAria || pInner, pDataId);
                        }
                    }

                    if (!detailWebsite) {
                        const webBtn = await detailPage.$('a[data-item-id="authority"], a[aria-label*="website" i]');
                        if (webBtn) {
                            detailWebsite = await webBtn.getAttribute('href');
                        }
                    }

                    const detailUrlMeta = extractCoordinatesAndPlaceId(detailPage.url());
                    if (detailUrlMeta.latitude !== null) detailLat = detailUrlMeta.latitude;
                    if (detailUrlMeta.longitude !== null) detailLng = detailUrlMeta.longitude;
                    if (detailUrlMeta.googlePlaceId) detailPlaceId = detailUrlMeta.googlePlaceId;

                } catch (detailErr) {
                    log(`Place detail fetch timeout for "${candidate.name}", falling back to card data.`);
                }
            }

            const rawAddressToParse = fullAddressText || candidate.cardStreetAddress;
            const parsed = parseAddressString(rawAddressToParse, city, state);

            finalResults.push({
                name: candidate.name,
                street_address: parsed.street_address,
                city: parsed.city,
                state: parsed.state,
                postal_code: parsed.postal_code,
                phone: detailPhone,
                website: detailWebsite,
                latitude: detailLat,
                longitude: detailLng,
                google_place_id: detailPlaceId,
                external_source_url: candidate.href || null,
            });

            log(`Accepted Pharmacy [${finalResults.length}${isUnlimited ? '' : '/' + limit}]: ${candidate.name} -> ${parsed.street_address || 'No street'}, ${parsed.city}, ${parsed.state} ${parsed.postal_code || ''}`);
        }

        await detailPage.close();
        log(`Pharmacy scraping complete. Total valid unique pharmacies: ${finalResults.length}`);

        outputJson({
            status: 'success',
            results: finalResults,
            stop_reason: overallStopReason,
            meta: {
                scraper_version: SCRAPER_VERSION,
                type: 'pharmacies',
                requested_limit: limit,
                is_unlimited: isUnlimited,
                raw_candidates: totalRawCandidates,
                accepted_candidates: candidateMap.size,
                duplicates_removed: totalDuplicates,
                final_unique: finalResults.length,
                stop_reason: overallStopReason,
                city: city,
                state: state,
                queries: queryDiagnostics,
            }
        });

        await browser.close();
    } catch (err) {
        log('Fatal scraper error:', err);
        if (browser) {
            try { await browser.close(); } catch (e) { }
        }
        outputJson({
            status: 'error',
            results: [],
            stop_reason: 'scraper_error',
            message: `Scraper process error: ${err.message}`,
            meta: {
                scraper_version: SCRAPER_VERSION,
                type: 'pharmacies',
                requested_limit: limit,
                is_unlimited: isUnlimited,
                raw_candidates: 0,
                accepted_candidates: 0,
                duplicates_removed: 0,
                final_unique: 0,
                stop_reason: 'scraper_error'
            }
        });
    }
}

run();
