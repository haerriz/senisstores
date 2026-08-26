(function () {
    'use strict';
    if (window.__haerrizAgenticCommerceInitialized) return;
    window.__haerrizAgenticCommerceInitialized = true;

    function bootRoot(root) {
        if (!root || root.dataset.initialized === '1') return;
        root.dataset.initialized = '1';

        const form = root.querySelector('[data-agentic-form]');
        const input = root.querySelector('[data-agentic-input]');
        const send = root.querySelector('[data-agentic-send]');
        const messages = root.querySelector('[data-agentic-messages]');
        const filters = root.querySelector('[data-agentic-filters]');
        const status = root.querySelector('[data-agentic-status]');
        const reset = root.querySelector('[data-agentic-reset]');
        const historyButton = root.querySelector('[data-agentic-history-button]');
        const historyPanel = root.querySelector('[data-agentic-history]');
        const historyClose = root.querySelector('[data-agentic-history-close]');
        const historyList = root.querySelector('[data-agentic-history-list]');
        const storageKey = 'Haerriz_AgenticCommerce:v2:' + (root.dataset.storeCode || 'default');
        const welcome = root.dataset.welcomeMessage || 'Tell me what you are looking for.';
        let state = {client_id: '', conversation_id: '', filters: [], query_phrase: '', cart_id: ''};

        function randomId() {
            try {
                const secureCrypto = window.crypto;
                if (secureCrypto && typeof secureCrypto.randomUUID === 'function') {
                    return secureCrypto.randomUUID().replace(/-/g, '') + secureCrypto.randomUUID().replace(/-/g, '').slice(0, 16);
                }
                if (secureCrypto && typeof secureCrypto.getRandomValues === 'function') {
                    const bytes = new Uint8Array(36);
                    secureCrypto.getRandomValues(bytes);
                    const alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_-';
                    let out = '';
                    bytes.forEach(function (byte) { out += alphabet[byte & 63]; });
                    return out;
                }
            } catch (e) {}
            // Never downgrade a guest-history bearer identifier to Math.random().
            // An empty value lets Magento generate the high-entropy client id server-side on the first request.
            return '';
        }

        try {
            const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
            if (saved && typeof saved === 'object') state = Object.assign(state, saved);
        } catch (e) {}
        if (!state.client_id || state.client_id.length < 20) state.client_id = randomId();

        function persist() {
            try { localStorage.setItem(storageKey, JSON.stringify(state)); } catch (e) {}
        }

        function cookieValue(name) {
            const prefix = encodeURIComponent(name) + '=';
            const parts = String(document.cookie || '').split(';');
            for (let i = 0; i < parts.length; i++) {
                const part = parts[i].trim();
                if (part.indexOf(prefix) === 0) {
                    try { return decodeURIComponent(part.slice(prefix.length)); } catch (e) { return part.slice(prefix.length); }
                }
            }
            return '';
        }

        function currentFormKey() {
            return cookieValue('form_key') || root.dataset.formKey || '';
        }

        function contrastText(color) {
            let rgb = null;
            const hex = String(color || '').trim().match(/^#([0-9a-f]{6})$/i);
            if (hex) {
                const value = parseInt(hex[1], 16);
                rgb = [(value >> 16) & 255, (value >> 8) & 255, value & 255];
            } else {
                const match = String(color || '').match(/rgba?\(\s*(\d+(?:\.\d+)?)\s*[, ]\s*(\d+(?:\.\d+)?)\s*[, ]\s*(\d+(?:\.\d+)?)/i);
                if (match) rgb = [Number(match[1]), Number(match[2]), Number(match[3])];
            }
            if (!rgb) return '#ffffff';
            const linear = rgb.map(function (channel) {
                const v = Math.max(0, Math.min(255, channel)) / 255;
                return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
            });
            const luminance = 0.2126 * linear[0] + 0.7152 * linear[1] + 0.0722 * linear[2];
            return luminance > 0.45 ? '#111111' : '#ffffff';
        }

        function applyTheme() {
            const bodyStyle = getComputedStyle(document.body);
            let bg = bodyStyle.backgroundColor;
            if (!bg || bg === 'rgba(0, 0, 0, 0)' || bg === 'transparent') {
                const htmlBg = getComputedStyle(document.documentElement).backgroundColor;
                bg = htmlBg && htmlBg !== 'rgba(0, 0, 0, 0)' && htmlBg !== 'transparent' ? htmlBg : '#ffffff';
            }
            root.style.setProperty('--ac-theme-bg', bg);
            root.style.setProperty('--ac-theme-text', bodyStyle.color || '#222222');
            const configured = (root.dataset.accentColor || '').trim();
            if (/^#[0-9a-f]{6}$/i.test(configured)) {
                root.style.setProperty('--ac-accent', configured);
                root.style.setProperty('--ac-on-accent', contrastText(configured));
                return;
            }
            const probe = Array.prototype.slice.call(document.querySelectorAll('.action.primary, button.primary, .btn-primary, button[type="submit"], a.action.primary')).find(function (el) { return !root.contains(el); });
            if (probe) {
                const probeStyle = getComputedStyle(probe);
                if (probeStyle.backgroundColor && probeStyle.backgroundColor !== 'rgba(0, 0, 0, 0)') {
                    root.style.setProperty('--ac-accent', probeStyle.backgroundColor);
                    root.style.setProperty('--ac-on-accent', probeStyle.color || '#fff');
                    return;
                }
            }
            const link = document.querySelector('a[href]');
            if (link) {
                const linkColor = getComputedStyle(link).color;
                root.style.setProperty('--ac-accent', linkColor);
                root.style.setProperty('--ac-on-accent', contrastText(linkColor));
            }
        }
        applyTheme();

        const placementSelector = (root.dataset.placementSelector || '').trim();
        if (placementSelector) {
            try {
                const hero = document.querySelector(placementSelector);
                if (hero && hero.parentNode && hero.nextElementSibling !== root) hero.insertAdjacentElement('afterend', root);
            } catch (e) {}
        }

        function text(tag, className, value) {
            const el = document.createElement(tag);
            if (className) el.className = className;
            el.textContent = value == null ? '' : String(value);
            return el;
        }

        function emitAgentEvent(name, detail) {
            const payload = Object.assign({
                conversation_id: state.conversation_id || '',
                client_id: state.client_id || ''
            }, detail || {});
            try {
                root.dispatchEvent(new CustomEvent('haerriz:agentic:' + name, {bubbles: true, detail: payload}));
            } catch (e) {}
        }

        function ordinal(index) {
            const n = index + 1;
            const mod100 = n % 100;
            if (mod100 >= 11 && mod100 <= 13) return n + 'th';
            return n + ({1:'st',2:'nd',3:'rd'}[n % 10] || 'th');
        }

        function renderProductGrid(items, interactive) {
            const grid = text('div', 'agentic-commerce__products', '');
            const selected = [];
            const compareBar = text('div', 'agentic-commerce__compare-bar', '');

            function updateCompareBar() {
                compareBar.innerHTML = '';
                if (!selected.length || interactive === false) return;
                compareBar.appendChild(text('span', 'agentic-commerce__compare-count', selected.length + ' selected for comparison'));
                if (selected.length >= 2) {
                    const compare = text('button', 'agentic-commerce__product-button', 'Compare selected');
                    compare.type = 'button';
                    compare.addEventListener('click', function () {
                        executeDirect('compare_products', {skus:selected.slice(0,4), focus:[]}, 'Compare selected products');
                    });
                    compareBar.appendChild(compare);
                }
                const clear = text('button', 'agentic-commerce__product-button agentic-commerce__product-link', 'Clear');
                clear.type = 'button';
                clear.addEventListener('click', function () {
                    selected.splice(0, selected.length);
                    grid.querySelectorAll('[data-compare-sku]').forEach(function (button) { button.setAttribute('aria-pressed','false'); button.textContent='Compare'; });
                    updateCompareBar();
                });
                compareBar.appendChild(clear);
            }

            items.slice(0, 24).forEach(function (p, index) {
                const card = text('article', 'agentic-commerce__product', '');
                const imageLink = document.createElement('a');
                imageLink.className = 'agentic-commerce__product-image-wrap';
                imageLink.href = p.url || '#';
                imageLink.addEventListener('click', function () { emitAgentEvent('product-click', {sku: p.sku || '', position: index + 1, url: p.url || ''}); });
                if (p.image) {
                    const img = document.createElement('img');
                    img.className = 'agentic-commerce__product-image';
                    img.src = p.image;
                    img.alt = p.name || '';
                    img.loading = 'lazy';
                    imageLink.appendChild(img);
                }
                const body = text('div', 'agentic-commerce__product-body', '');
                const name = text('h3', 'agentic-commerce__product-name', '');
                const productLink = text('a', '', p.name || p.sku || 'Product');
                productLink.href = p.url || '#';
                productLink.addEventListener('click', function () { emitAgentEvent('product-click', {sku: p.sku || '', position: index + 1, url: p.url || ''}); });
                name.appendChild(productLink);
                body.appendChild(name);
                const price = text('div', 'agentic-commerce__price', p.formatted_price || p.price || '');
                if (p.formatted_regular_price && p.formatted_regular_price !== p.formatted_price) {
                    price.appendChild(text('span', 'agentic-commerce__regular-price', p.formatted_regular_price));
                }
                body.appendChild(price);
                if (p.inventory && p.inventory.message) body.appendChild(text('div', 'agentic-commerce__availability', p.inventory.message));
                else if (p.is_salable === false) body.appendChild(text('div', 'agentic-commerce__availability', 'Currently unavailable'));
                if (Array.isArray(p.custom_attributes) && p.custom_attributes.length) {
                    const attrs = text('ul', 'agentic-commerce__attrs', '');
                    p.custom_attributes.slice(0, 5).forEach(function (a) { attrs.appendChild(text('li', '', (a.label || a.code) + ': ' + a.value)); });
                    body.appendChild(attrs);
                }
                if (Array.isArray(p.match_reasons) && p.match_reasons.length) {
                    const reasons = text('ul', 'agentic-commerce__reasons', '');
                    p.match_reasons.slice(0, 4).forEach(function (reason) { reasons.appendChild(text('li', '', reason)); });
                    body.appendChild(reasons);
                }
                const cardActions = text('div', 'agentic-commerce__product-actions', '');
                if (interactive !== false && p.sku && p.is_salable !== false) {
                    const add = text('button', 'agentic-commerce__product-button', 'Add to cart');
                    add.type = 'button';
                    add.addEventListener('click', function () { emitAgentEvent('product-action', {action: 'add_to_cart', sku: String(p.sku)}); executeDirect('add_to_cart', {sku:String(p.sku), quantity:1}, 'Add ' + (p.name || p.sku) + ' to cart'); });
                    cardActions.appendChild(add);
                }
                if (interactive !== false && p.sku) {
                    const details = text('button', 'agentic-commerce__product-button agentic-commerce__product-link', 'Details');
                    details.type = 'button';
                    details.addEventListener('click', function () { executeDirect('get_product_content', {sku:String(p.sku)}, 'Describe ' + (p.name || p.sku)); });
                    cardActions.appendChild(details);

                    const compare = text('button', 'agentic-commerce__product-button agentic-commerce__product-link', 'Compare');
                    compare.type = 'button'; compare.dataset.compareSku = String(p.sku); compare.setAttribute('aria-pressed','false');
                    compare.addEventListener('click', function () {
                        const sku=String(p.sku); const at=selected.indexOf(sku);
                        if (at >= 0) { selected.splice(at,1); compare.setAttribute('aria-pressed','false'); compare.textContent='Compare'; }
                        else if (selected.length < 4) { selected.push(sku); compare.setAttribute('aria-pressed','true'); compare.textContent='Selected'; }
                        updateCompareBar();
                    });
                    cardActions.appendChild(compare);

                    const save = text('button', 'agentic-commerce__product-button agentic-commerce__product-link', 'Save');
                    save.type = 'button';
                    save.addEventListener('click', function () { emitAgentEvent('product-action', {action: 'wishlist', sku: String(p.sku)}); executeDirect('add_to_wishlist', {sku:String(p.sku)}, 'Save ' + (p.name || p.sku) + ' to wishlist'); });
                    cardActions.appendChild(save);
                }
                const open = text('a', 'agentic-commerce__product-button agentic-commerce__product-link', 'View');
                open.href = p.url || '#';
                cardActions.appendChild(open);
                body.appendChild(cardActions);
                card.appendChild(imageLink);
                card.appendChild(body);
                grid.appendChild(card);
            });
            grid.appendChild(compareBar);
            updateCompareBar();
            return grid;
        }

        function renderProductContent(data) {
            if (!data || !data.product) return null;
            const box = text('div', 'agentic-commerce__product-content', '');
            box.appendChild(text('div', 'agentic-commerce__section-title', (data.product.name || data.product.sku || 'Product') + ' details'));
            if (data.short_description) { box.appendChild(text('div','agentic-commerce__content-label','Short description')); box.appendChild(text('p','agentic-commerce__content-copy',data.short_description)); }
            if (data.description) { box.appendChild(text('div','agentic-commerce__content-label','Description')); box.appendChild(text('p','agentic-commerce__content-copy',data.description)); }
            if (Array.isArray(data.highlights) && data.highlights.length) {
                const list=text('ul','agentic-commerce__comparison-list',''); data.highlights.slice(0,12).forEach(function(v){list.appendChild(text('li','',v));}); box.appendChild(list);
            }
            if (Array.isArray(data.media_gallery) && data.media_gallery.length) {
                const gallery=text('div','agentic-commerce__media-gallery',''); data.media_gallery.slice(0,8).forEach(function(media){if(!media||!media.url)return; const a=document.createElement('a');a.href=media.url;a.target='_blank';a.rel='noopener';const img=document.createElement('img');img.src=media.url;img.alt=media.label||data.product.name||'';img.loading='lazy';a.appendChild(img);gallery.appendChild(a);}); if(gallery.children.length)box.appendChild(gallery);
            }
            if (Array.isArray(data.specifications) && data.specifications.length) {
                const specs=text('dl','agentic-commerce__specs',''); data.specifications.slice(0,30).forEach(function(a){specs.appendChild(text('dt','',a.label||a.code||'')); specs.appendChild(text('dd','',a.value||''));}); box.appendChild(specs);
            }
            return box;
        }

        function renderProductAnswer(data) {
            if (!data || !data.answer) return null;
            const box=text('div','agentic-commerce__product-answer',''); box.appendChild(text('div','agentic-commerce__section-title','Product evidence'));
            box.appendChild(text('p','agentic-commerce__content-copy',data.answer));
            if(Array.isArray(data.evidence)&&data.evidence.length){ const list=text('ul','agentic-commerce__comparison-list',''); data.evidence.slice(0,4).forEach(function(e){list.appendChild(text('li','',(e.source?e.source.replace(/_/g,' ')+': ':'')+(e.text||'')));}); box.appendChild(list); }
            return box;
        }

        function renderComparison(data) {
            if (!data || !Array.isArray(data.rows) || !Array.isArray(data.products)) return null;
            const box=text('div','agentic-commerce__comparison',''); box.appendChild(text('div','agentic-commerce__section-title','Product comparison'));
            const table=document.createElement('table'); table.className='agentic-commerce__comparison-table';
            const thead=document.createElement('thead'); const hr=document.createElement('tr'); hr.appendChild(text('th','','Compare'));
            data.products.forEach(function(p){hr.appendChild(text('th','',p.name||p.sku||'Product'));}); thead.appendChild(hr); table.appendChild(thead);
            const tbody=document.createElement('tbody');
            data.rows.slice(0,40).forEach(function(row){ const tr=document.createElement('tr'); tr.appendChild(text('th','',row.label||row.key||'')); const bySku={}; (row.values||[]).forEach(function(v){bySku[String(v.sku||'')]=v.value||'';}); data.products.forEach(function(p){tr.appendChild(text('td','',bySku[String(p.sku||'')]||'—'));}); tbody.appendChild(tr); });
            table.appendChild(tbody); const scroller=text('div','agentic-commerce__comparison-scroll',''); scroller.appendChild(table); box.appendChild(scroller);
            if(Array.isArray(data.similarities)&&data.similarities.length){box.appendChild(text('div','agentic-commerce__content-label','Similarities')); const list=text('ul','agentic-commerce__comparison-list',''); data.similarities.slice(0,12).forEach(function(v){list.appendChild(text('li','',v));}); box.appendChild(list);}
            if(Array.isArray(data.differences)&&data.differences.length){box.appendChild(text('div','agentic-commerce__content-label','Different areas')); box.appendChild(text('p','agentic-commerce__content-copy',data.differences.slice(0,20).join(', ')));}
            if(data.goal && Array.isArray(data.goal_assessment) && data.goal_assessment.length){
                box.appendChild(text('div','agentic-commerce__content-label','Fit for: ' + data.goal));
                const fit=text('div','agentic-commerce__goal-fit','');
                data.goal_assessment.slice(0,4).forEach(function(row){
                    const item=text('div','agentic-commerce__goal-fit-item','');
                    item.appendChild(text('strong','',row.name||row.sku||'Product'));
                    item.appendChild(text('span','agentic-commerce__meta','Evidence score: ' + String(row.score||0)));
                    if(Array.isArray(row.evidence)&&row.evidence.length){item.appendChild(text('p','agentic-commerce__content-copy',row.evidence.slice(0,2).join(' · ')));}
                    fit.appendChild(item);
                });
                box.appendChild(fit);
                box.appendChild(text('p','agentic-commerce__meta','Fit scores reflect explicit catalog wording only; they are not subjective quality ratings.'));
            }
            return box;
        }

        function collectProductOptionSelections(box) {
            const selections = [];
            box.querySelectorAll('[data-option-code]').forEach(function (row) {
                const code = row.dataset.optionCode || ''; const control = row.querySelector('[data-option-control]'); if (!code || !control) return;
                let values = [];
                if (control.tagName === 'SELECT' && control.multiple) values = Array.prototype.slice.call(control.selectedOptions).map(function (o) { return o.value; }).filter(Boolean);
                else if (String(control.value || '').trim() !== '') values = [String(control.value).trim()];
                if (values.length) selections.push({code:code, values:values});
            });
            return selections;
        }

        function renderProductOptions(options, interactive) {
            if (!options || !Array.isArray(options.groups) || !options.groups.length) return null;
            const box = text('form', 'agentic-commerce__options', '');
            box.addEventListener('submit', function (event) { event.preventDefault(); });
            box.appendChild(text('div', 'agentic-commerce__section-title', 'Choose options · ' + (options.name || options.sku || 'Product')));
            if (options.chat_supported === false) {
                box.appendChild(text('div', 'agentic-commerce__notice', 'One or more required options must be completed on the product page.'));
            }
            options.groups.forEach(function (group) {
                const row = text('label', 'agentic-commerce__option-group', '');
                row.dataset.optionCode = group.code || '';
                row.dataset.inputMode = group.input_mode || 'select';
                row.appendChild(text('span', 'agentic-commerce__option-label', (group.label || group.code) + (group.required ? ' *' : '')));
                let control = null;
                const mode = group.input_mode || 'select';
                if (mode === 'select' || mode === 'multi') {
                    control = document.createElement('select');
                    control.className = 'agentic-commerce__option-control';
                    if (mode === 'multi' || group.multiple) control.multiple = true;
                    if (!control.multiple) {
                        const placeholder = document.createElement('option'); placeholder.value = ''; placeholder.textContent = 'Choose…'; control.appendChild(placeholder);
                    }
                    (Array.isArray(group.values) ? group.values : []).forEach(function (value) {
                        const option = document.createElement('option'); option.value = String(value.value || '');
                        option.textContent = (value.label || value.value || '') + (value.price ? ' +' + value.price : ''); control.appendChild(option);
                    });
                } else if (mode === 'textarea') {
                    control = document.createElement('textarea'); control.rows = 3; control.className = 'agentic-commerce__option-control';
                } else if (mode === 'quantity') {
                    control = document.createElement('input'); control.type = 'number'; control.min = '0'; control.max = '100'; control.step = '1'; control.value = '0'; control.className = 'agentic-commerce__option-control';
                } else if (mode === 'file') {
                    control = text('span', 'agentic-commerce__notice', 'Open the product page to upload this file.');
                } else {
                    control = document.createElement('input'); control.type = 'text'; control.className = 'agentic-commerce__option-control';
                }
                if (control && control.tagName && ['INPUT','SELECT','TEXTAREA'].indexOf(control.tagName) !== -1) {
                    control.dataset.optionControl = '1'; control.disabled = interactive === false || group.chat_supported === false;
                    if (group.required && group.chat_supported !== false && mode !== 'quantity') control.required = true;
                }
                row.appendChild(control); box.appendChild(row);
            });
            if (interactive !== false && options.chat_supported !== false) {
                const add = text('button', 'agentic-commerce__product-button', 'Add selected configuration to cart'); add.type = 'button';
                add.addEventListener('click', function () {
                    const selections = collectProductOptionSelections(box);
                    executeDirect('add_to_cart', {sku:String(options.sku || ''), quantity:1, selections:selections}, 'Add configured ' + (options.name || options.sku || 'product') + ' to cart');
                });
                box.appendChild(add);
                if (String(options.type || '') === 'configurable') {
                    const check = text('button', 'agentic-commerce__product-button agentic-commerce__product-link', 'Check availability'); check.type = 'button';
                    check.addEventListener('click', function () {
                        executeDirect('get_variant_availability', {sku:String(options.sku || ''), requested_qty:1, selections:collectProductOptionSelections(box)}, 'Check selected variant availability');
                    });
                    box.appendChild(check);
                }
            }
            return box;
        }

        function renderVariantAvailability(data) {
            if (!data || !Array.isArray(data.candidates)) return null;
            const box = text('div', 'agentic-commerce__variant-availability', '');
            box.appendChild(text('div', 'agentic-commerce__section-title', 'Variant availability'));
            if (data.assistant_message) box.appendChild(text('div', 'agentic-commerce__notice', data.assistant_message));
            if (Array.isArray(data.selected) && data.selected.length) {
                box.appendChild(text('div', 'agentic-commerce__meta', 'Selected: ' + data.selected.map(function (x) { return (x.code || '') + ': ' + (x.value || ''); }).join(' · ')));
            }
            if (Array.isArray(data.missing_attributes) && data.missing_attributes.length) {
                box.appendChild(text('div', 'agentic-commerce__meta', 'Still choose: ' + data.missing_attributes.join(', ')));
            }
            data.candidates.slice(0, 12).forEach(function (candidate) {
                const row = text('div', 'agentic-commerce__inventory-row', '');
                row.appendChild(text('strong', '', candidate.name || candidate.sku || 'Variant'));
                if (Array.isArray(candidate.attributes) && candidate.attributes.length) row.appendChild(text('div', 'agentic-commerce__meta', candidate.attributes.map(function (a) { return (a.code || '') + ': ' + (a.value || ''); }).join(' · ')));
                if (candidate.inventory) row.appendChild(text('div', 'agentic-commerce__stock', candidate.inventory.message || candidate.inventory.status || ''));
                if (candidate.price) row.appendChild(text('div', 'agentic-commerce__price', candidate.price.formatted_final_price || ''));
                box.appendChild(row);
            });
            return box;
        }

        function renderAddressSummary(address, label) {
            if (!address) return null;
            const box = text('div', 'agentic-commerce__address-summary', '');
            box.appendChild(text('strong', '', label));
            const line = [address.firstname, address.lastname, Array.isArray(address.street) ? address.street.join(', ') : '', address.city, address.region, address.postcode, address.country_id].filter(Boolean).join(', ');
            box.appendChild(text('div', '', line));
            return box;
        }

        function renderAddressForm(kind) {
            const form = text('form', 'agentic-commerce__address-form', ''); form.addEventListener('submit', function (e) { e.preventDefault(); });
            form.appendChild(text('div', 'agentic-commerce__section-title', (kind === 'shipping' ? 'Shipping' : 'Billing') + ' address'));
            const fields = [
                ['firstname','First name','text'],['lastname','Last name','text'],['company','Company','text'],['street','Street','text'],['city','City','text'],['region','State / Province','text'],['postcode','Postal code','text'],['country_id','Country code','text'],['telephone','Telephone','tel']
            ];
            fields.forEach(function (spec) { const label = text('label','agentic-commerce__field',''); label.appendChild(text('span','',spec[1])); const input = document.createElement('input'); input.name = spec[0]; input.type = spec[2]; input.className='agentic-commerce__option-control'; if (['company','region'].indexOf(spec[0])===-1) input.required=true; if(spec[0]==='country_id') input.maxLength=2; label.appendChild(input); form.appendChild(label); });
            const save = text('button','agentic-commerce__product-button','Use this address'); save.type='button'; save.addEventListener('click', function () {
                if (!form.reportValidity()) return; const address={}; fields.forEach(function(spec){ const v=form.elements[spec[0]].value.trim(); address[spec[0]]=spec[0]==='street'?[v]:v; });
                executeDirect('set_checkout_address',{kind:kind,address:address},'Set ' + kind + ' address');
            }); form.appendChild(save); return form;
        }

        function renderCheckout(checkout, interactive) {
            if (!checkout || typeof checkout !== 'object') return null;
            const box = text('div','agentic-commerce__checkout',''); box.appendChild(text('div','agentic-commerce__section-title','Checkout'));
            const missing = Array.isArray(checkout.missing) ? checkout.missing : [];
            box.appendChild(text('div','agentic-commerce__checkout-status', checkout.ready ? 'Ready to place order' : ('Still needed: ' + (missing.join(', ') || 'checkout information'))));
            const ship = renderAddressSummary(checkout.shipping_address,'Shipping'); if(ship) box.appendChild(ship);
            const bill = renderAddressSummary(checkout.billing_address,'Billing'); if(bill) box.appendChild(bill);
            if (interactive !== false && missing.indexOf('guest_email') !== -1) {
                const row=text('div','agentic-commerce__inline-form',''); const email=document.createElement('input'); email.type='email'; email.placeholder='Checkout email'; email.className='agentic-commerce__option-control'; const save=text('button','agentic-commerce__product-button','Save email'); save.type='button'; save.addEventListener('click',function(){ if(!email.value.trim())return; executeDirect('set_guest_email',{email:email.value.trim()},'Set checkout email'); }); row.appendChild(email); row.appendChild(save); box.appendChild(row);
            }
            if (interactive !== false && missing.indexOf('shipping_address') !== -1) box.appendChild(renderAddressForm('shipping'));
            if (interactive !== false && missing.indexOf('billing_address') !== -1) box.appendChild(renderAddressForm('billing'));
            if (Array.isArray(checkout.available_shipping_methods) && checkout.available_shipping_methods.length) {
                const methods=text('div','agentic-commerce__method-list',''); methods.appendChild(text('strong','','Shipping methods'));
                checkout.available_shipping_methods.forEach(function(m){ const b=text('button','agentic-commerce__suggestion',(m.carrier_title||m.carrier_code)+' · '+(m.method_title||m.method_code)+(m.formatted_amount?' · '+m.formatted_amount:'')); b.type='button'; b.disabled=interactive===false; b.addEventListener('click',function(){executeDirect('set_shipping_method',{carrier_code:m.carrier_code,method_code:m.method_code},'Use '+(m.method_title||m.method_code)+' shipping');}); methods.appendChild(b); }); box.appendChild(methods);
            }
            if (Array.isArray(checkout.available_payment_methods) && checkout.available_payment_methods.length) {
                const methods=text('div','agentic-commerce__method-list',''); methods.appendChild(text('strong','','Payment methods'));
                checkout.available_payment_methods.forEach(function(m){ const b=text('button','agentic-commerce__suggestion',m.title||m.code); b.type='button'; b.disabled=interactive===false; b.addEventListener('click',function(){executeDirect('set_payment_method',{method_code:m.code},'Use '+(m.title||m.code)+' payment');}); methods.appendChild(b); }); box.appendChild(methods);
                box.appendChild(text('div','agentic-commerce__notice','Sensitive payment credentials are entered only in the payment provider UI, never in AI chat.'));
            }
            return box;
        }

        function renderConfirmation(confirmation, interactive) {
            if (!confirmation || !confirmation.token) return null;
            const box=text('div','agentic-commerce__confirmation',''); box.appendChild(text('strong','',confirmation.title||'Confirmation required')); box.appendChild(text('div','',confirmation.summary||''));
            if(interactive!==false){ const confirm=text('button','agentic-commerce__product-button','Confirm'); confirm.type='button'; confirm.addEventListener('click',function(){executeDirect('confirm_action',{token:confirmation.token},'Confirm action');}); box.appendChild(confirm); const cancel=text('button','agentic-commerce__product-button agentic-commerce__product-link','Cancel'); cancel.type='button'; cancel.addEventListener('click',function(){sendMessage('Cancel the pending confirmation');}); box.appendChild(cancel); }
            return box;
        }

        function renderCustomer(customer) {
            if(!customer) return null; const box=text('div','agentic-commerce__customer',''); box.appendChild(text('div','agentic-commerce__section-title','Account')); box.appendChild(text('div','',(customer.firstname||'')+' '+(customer.lastname||''))); if(customer.email)box.appendChild(text('div','',customer.email)); return box;
        }

        function renderAddresses(addresses, interactive) {
            if(!Array.isArray(addresses)||!addresses.length)return null; const box=text('div','agentic-commerce__addresses',''); box.appendChild(text('div','agentic-commerce__section-title','Saved addresses')); addresses.slice(0,10).forEach(function(a,i){const row=renderAddressSummary(a,(i+1)+'.'+(a.default_shipping?' Default shipping':'')+(a.default_billing?' Default billing':'')); if(row){ if(interactive!==false&&a.id){ const actions=text('div','agentic-commerce__product-actions',''); const edit=text('button','agentic-commerce__product-button agentic-commerce__product-link','Edit'); edit.type='button'; edit.addEventListener('click',function(){sendMessage('edit my '+ordinal(i)+' saved address');}); actions.appendChild(edit); const del=text('button','agentic-commerce__product-button agentic-commerce__product-link','Delete'); del.type='button'; del.addEventListener('click',function(){executeDirect('prepare_delete_customer_address',{address_id:Number(a.id)},'Delete saved address');}); actions.appendChild(del); row.appendChild(actions);} box.appendChild(row); }}); return box;
        }


        function renderInventories(items) {
            if (!Array.isArray(items) || !items.length) return null;
            const box = text('div','agentic-commerce__inventory-list','');
            box.appendChild(text('div','agentic-commerce__section-title','Availability comparison'));
            items.forEach(function (inventory) {
                const row = text('div','agentic-commerce__availability-row','');
                row.appendChild(text('strong','', inventory.name || inventory.sku || 'Product'));
                row.appendChild(text('span','', ' · ' + (inventory.message || (inventory.is_salable ? 'In stock' : 'Out of stock'))));
                box.appendChild(row);
            });
            return box;
        }

        function renderInventory(inventory) {
            if (!inventory || typeof inventory !== 'object') return null;
            const box = text('div','agentic-commerce__inventory','');
            box.appendChild(text('div','agentic-commerce__section-title','Availability · ' + (inventory.sku || 'Product')));
            box.appendChild(text('div','agentic-commerce__availability',inventory.message || (inventory.is_salable ? 'In stock' : 'Out of stock')));
            if (inventory.quantity_exposed && inventory.salable_qty !== null && inventory.salable_qty !== undefined) {
                box.appendChild(text('small','', 'Salable quantity: ' + inventory.salable_qty + (inventory.quantity_source ? ' · ' + inventory.quantity_source : '')));
            }
            if (inventory.requested_qty && inventory.requested_qty > 1) {
                box.appendChild(text('div','',inventory.requested_qty_salable ? ('Yes, ' + inventory.requested_qty + ' can currently be purchased.') : ('The requested quantity ' + inventory.requested_qty + ' is not currently salable.')));
            }
            return box;
        }

        function renderPriceInsight(price) {
            if (!price || typeof price !== 'object') return null;
            const box=text('div','agentic-commerce__price-insight',''); box.appendChild(text('div','agentic-commerce__section-title','Price · '+(price.name||price.sku||'Product')));
            box.appendChild(text('div','agentic-commerce__price',price.formatted_final_price||price.final_price||''));
            if(Number(price.discount_amount||0)>0){box.appendChild(text('div','', (price.discount_percent||0)+'% off · save '+(price.formatted_discount_amount||price.discount_amount)));}
            if(Array.isArray(price.tier_prices)&&price.tier_prices.length){const tiers=text('ul','agentic-commerce__attrs','');price.tier_prices.slice(0,8).forEach(function(t){tiers.appendChild(text('li','','Buy '+t.qty+'+ · '+(t.formatted_value||t.value)));});box.appendChild(tiers);}
            return box;
        }

        function renderStructuredForm(form, interactive) {
            if (!form || !Array.isArray(form.fields)) return null;
            const box=text('form','agentic-commerce__structured-form',''); box.addEventListener('submit',function(e){e.preventDefault();}); box.appendChild(text('div','agentic-commerce__section-title',form.title||'Details'));
            form.fields.forEach(function(field){const label=text('label','agentic-commerce__option-group',''); label.appendChild(text('span','agentic-commerce__option-label',(field.label||field.name)+(field.required?' *':''))); let control;
                if(field.type==='select'){control=document.createElement('select'); const blank=document.createElement('option');blank.value='';blank.textContent='Choose…';control.appendChild(blank);(field.options||[]).forEach(function(o){const opt=document.createElement('option');opt.value=String(o.value||'');opt.textContent=o.label||o.value||'';if(String(field.value||'')===opt.value)opt.selected=true;control.appendChild(opt);});}
                else {control=document.createElement('input');control.type=field.type==='checkbox'?'checkbox':(field.type||'text'); if(control.type==='checkbox')control.checked=String(field.value||'')==='1'; else control.value=field.value||'';}
                control.className='agentic-commerce__option-control';control.name=field.name||'';control.required=!!field.required&&control.type!=='checkbox';control.disabled=interactive===false;label.appendChild(control);box.appendChild(label);
            });
            if(interactive!==false){const submit=text('button','agentic-commerce__product-button',form.submit_label||'Save');submit.type='button';submit.addEventListener('click',function(){if(!box.reportValidity())return;const data={};(form.hidden||[]).forEach(function(h){if(h&&h.code)data[h.code]=h.value;});box.querySelectorAll('[name]').forEach(function(c){data[c.name]=c.type==='checkbox'?!!c.checked:String(c.value||'').trim();});executeDirect(form.action,data,form.submit_label||'Save');});box.appendChild(submit);}
            return box;
        }

        function renderReviews(reviews) {
            if(!reviews||!Array.isArray(reviews.items))return null; const box=text('div','agentic-commerce__reviews',''); box.appendChild(text('div','agentic-commerce__section-title','Reviews · '+(reviews.total_count||0))); reviews.items.slice(0,8).forEach(function(r){const row=text('div','agentic-commerce__review',''); row.appendChild(text('strong','',r.title||'Review')); row.appendChild(text('p','',r.detail||'')); if(r.nickname)row.appendChild(text('small','',r.nickname)); box.appendChild(row);}); return box;
        }

        function renderCart(cart, interactive) {
            if (!cart || typeof cart !== 'object') return null;
            const box = text('div', 'agentic-commerce__cart', '');
            const head = text('div', 'agentic-commerce__cart-head', '');
            head.appendChild(text('span', '', (cart.items_count || 0) + ' item(s)'));
            head.appendChild(text('span', '', cart.formatted_grand_total || cart.formatted_subtotal || ''));
            box.appendChild(head);
            if (cart.coupon_code) box.appendChild(text('div', 'agentic-commerce__coupon', 'Coupon: ' + cart.coupon_code));
            const totals = [];
            if (Number(cart.discount_amount || 0) > 0) totals.push('Discount ' + (cart.formatted_discount_amount || cart.discount_amount));
            if (Number(cart.shipping_amount || 0) > 0) totals.push('Shipping ' + (cart.formatted_shipping_amount || cart.shipping_amount));
            if (Number(cart.tax_amount || 0) > 0) totals.push('Tax ' + (cart.formatted_tax_amount || cart.tax_amount));
            if (totals.length) box.appendChild(text('div','agentic-commerce__cart-totals',totals.join(' · ')));
            (Array.isArray(cart.items) ? cart.items : []).forEach(function (item, index) {
                const row = text('div', 'agentic-commerce__cart-item', '');
                row.appendChild(text('span', 'agentic-commerce__cart-name', (item.name || item.sku) + ' × ' + item.qty));
                if (interactive !== false) {
                    const remove = text('button', 'agentic-commerce__cart-remove', 'Remove');
                    remove.type = 'button';
                    remove.addEventListener('click', function () { executeDirect('remove_cart_item', {index:index + 1}, 'Remove ' + (item.name || item.sku) + ' from cart'); });
                    row.appendChild(remove);
                }
                box.appendChild(row);
            });
            return box;
        }

        function renderWishlist(wishlist, interactive) {
            if (!wishlist || !Array.isArray(wishlist.items)) return null;
            const box = text('div', 'agentic-commerce__wishlist', '');
            box.appendChild(text('div', 'agentic-commerce__section-title', 'Wishlist · ' + (wishlist.items_count || 0) + ' item(s)'));
            if (!wishlist.items.length) box.appendChild(text('div', 'agentic-commerce__empty', 'Your wishlist is empty.'));
            wishlist.items.slice(0, 12).forEach(function (item, index) {
                const row = text('div', 'agentic-commerce__wishlist-item', '');
                const product = item.product || item || {};
                const link = text('a', 'agentic-commerce__cart-name', product.name || product.sku || 'Product');
                link.href = product.url || '#';
                row.appendChild(link);
                if (interactive !== false) {
                    const remove = text('button', 'agentic-commerce__cart-remove', 'Remove');
                    remove.type = 'button';
                    remove.addEventListener('click', function () { executeDirect('remove_wishlist_item', {index:index + 1}, 'Remove ' + (product.name || product.sku) + ' from wishlist'); });
                    row.appendChild(remove);
                }
                box.appendChild(row);
            });
            return box;
        }

        function renderOrders(orders) {
            if (!Array.isArray(orders) || !orders.length) return null;
            const wrap = text('div', 'agentic-commerce__orders', '');
            wrap.appendChild(text('div', 'agentic-commerce__section-title', 'Orders'));
            orders.slice(0, 10).forEach(function (order) {
                const card = text('div', 'agentic-commerce__order', '');
                const head = text('div', 'agentic-commerce__order-head', '');
                head.appendChild(text('strong', '', '#' + (order.number || order.order_number || '')));
                head.appendChild(text('span', '', order.status_label || order.status || ''));
                card.appendChild(head);
                const meta = [order.created_at || '', order.formatted_grand_total || ''].filter(Boolean).join(' · ');
                if (meta) card.appendChild(text('div', 'agentic-commerce__order-meta', meta));
                if (Array.isArray(order.items) && order.items.length) {
                    order.items.slice(0, 8).forEach(function (item) {
                        card.appendChild(text('div', 'agentic-commerce__order-item', (item.name || item.sku) + ' × ' + (item.qty || item.qty_ordered || 0)));
                    });
                }
                if (Array.isArray(order.tracking) && order.tracking.length) {
                    order.tracking.forEach(function (track) {
                        card.appendChild(text('div', 'agentic-commerce__tracking', 'Tracking: ' + (track.carrier || '') + ' ' + (track.number || '')));
                    });
                }
                wrap.appendChild(card);
            });
            return wrap;
        }

        function renderKnowledge(items) {
            if (!Array.isArray(items) || !items.length) return null;
            const wrap = text('div', 'agentic-commerce__knowledge', '');
            wrap.appendChild(text('div', 'agentic-commerce__section-title', 'Store information'));
            items.slice(0, 5).forEach(function (item) {
                const card = text('div', 'agentic-commerce__knowledge-item', '');
                const link = text('a', 'agentic-commerce__knowledge-title', item.title || 'Read more');
                link.href = item.url || '#';
                card.appendChild(link);
                if (item.snippet || item.excerpt) card.appendChild(text('p', '', item.snippet || item.excerpt));
                wrap.appendChild(card);
            });
            return wrap;
        }

        function renderSuggestions(items, interactive) {
            if (interactive === false || !Array.isArray(items) || !items.length) return null;
            const wrap = text('div', 'agentic-commerce__suggestions', '');
            items.slice(0, 6).forEach(function (suggestion) {
                const button = text('button', 'agentic-commerce__suggestion', suggestion);
                button.type = 'button';
                button.addEventListener('click', function () { sendMessage(suggestion); });
                wrap.appendChild(button);
            });
            return wrap;
        }

        function renderActions(actions, interactive) {
            if (!Array.isArray(actions) || !actions.length) return null;
            const wrap = text('div', 'agentic-commerce__actions', '');
            let autoUrl = '';
            actions.forEach(function (a) {
                if (a.type !== 'navigate' || !a.url) return;
                const link = text('a', 'agentic-commerce__action', a.label || 'Open');
                link.href = a.url;
                wrap.appendChild(link);
                if (!autoUrl && interactive !== false && root.dataset.autoNavigation === '1' && a.auto_navigate === true) {
                    autoUrl = a.url;
                }
            });
            if (autoUrl) {
                window.setTimeout(function () { window.location.assign(autoUrl); }, 450);
            }
            return wrap.children.length ? wrap : null;
        }

        function addMessage(role, value, payload, scroll, interactive) {
            const article = text('article', 'agentic-commerce__message agentic-commerce__message--' + (role === 'user' ? 'user' : 'assistant'), '');
            article.appendChild(text('div', 'agentic-commerce__bubble', value));
            if (role !== 'user' && payload && typeof payload === 'object') {
                const payloadWrap = text('div', 'agentic-commerce__payload', '');
                if (Array.isArray(payload.products) && payload.products.length) payloadWrap.appendChild(renderProductGrid(payload.products, interactive));
                const comparison = renderComparison(payload.comparison);
                if (comparison) payloadWrap.appendChild(comparison);
                const productContent = renderProductContent(payload.product_content);
                if (productContent) payloadWrap.appendChild(productContent);
                const productAnswer = renderProductAnswer(payload.product_answer);
                if (productAnswer) payloadWrap.appendChild(productAnswer);
                const cart = renderCart(payload.cart, interactive);
                if (cart) payloadWrap.appendChild(cart);
                const wishlist = renderWishlist(payload.wishlist, interactive);
                if (wishlist) payloadWrap.appendChild(wishlist);
                const orders = renderOrders(payload.orders);
                if (orders) payloadWrap.appendChild(orders);
                const knowledge = renderKnowledge(payload.knowledge);
                if (knowledge) payloadWrap.appendChild(knowledge);
                const productOptions = renderProductOptions(payload.product_options, interactive);
                if (productOptions) payloadWrap.appendChild(productOptions);
                const variantAvailability = renderVariantAvailability(payload.variant_availability);
                if (variantAvailability) payloadWrap.appendChild(variantAvailability);
                const checkout = renderCheckout(payload.checkout, interactive);
                if (checkout) payloadWrap.appendChild(checkout);
                const customer = renderCustomer(payload.customer);
                if (customer) payloadWrap.appendChild(customer);
                const addresses = renderAddresses(payload.addresses, interactive);
                if (addresses) payloadWrap.appendChild(addresses);
                const inventory = renderInventory(payload.inventory);
                if (inventory) payloadWrap.appendChild(inventory);
                const inventories = renderInventories(payload.inventories);
                if (inventories) payloadWrap.appendChild(inventories);
                const priceInsight = renderPriceInsight(payload.price_insight);
                if (priceInsight) payloadWrap.appendChild(priceInsight);
                const structuredForm = renderStructuredForm(payload.form, interactive);
                if (structuredForm) payloadWrap.appendChild(structuredForm);
                const reviews = renderReviews(payload.reviews);
                if (reviews) payloadWrap.appendChild(reviews);
                const confirmation = renderConfirmation(payload.confirmation, interactive);
                if (confirmation) payloadWrap.appendChild(confirmation);
                const actionWrap = renderActions(payload.actions, interactive);
                if (actionWrap) payloadWrap.appendChild(actionWrap);
                const suggestions = renderSuggestions(payload.suggestions, interactive);
                if (suggestions) payloadWrap.appendChild(suggestions);
                if (payloadWrap.children.length) article.appendChild(payloadWrap);
            }
            messages.appendChild(article);
            if (scroll !== false) messages.scrollTop = messages.scrollHeight;
        }

        function showWelcome() {
            messages.innerHTML = '';
            addMessage('assistant', welcome, null, false);
        }

        function renderFilters(items) {
            filters.innerHTML = '';
            state.filters = Array.isArray(items) ? items : [];
            if (!state.filters.length) { filters.hidden = true; persist(); emitState(); return; }
            state.filters.forEach(function (f) {
                const values = Array.isArray(f.values) ? f.values.join(', ') : '';
                const chip = text('button', 'agentic-commerce__filter-chip', (f.label || f.attribute) + (values ? ': ' + values : ''));
                chip.type = 'button';
                chip.addEventListener('click', function () { sendMessage('Remove ' + (f.label || f.attribute) + ' filter'); });
                filters.appendChild(chip);
            });
            filters.hidden = false;
            persist();
            emitState();
        }

        function emitState() {
            try { root.dispatchEvent(new CustomEvent('haerriz:agentic:state', {bubbles:true, detail:Object.assign({}, state)})); } catch (e) {}
        }

        async function requestJson(url, options, networkRetries) {
            let response;
            try {
                response = await fetch(url, options || {credentials:'same-origin'});
            } catch (error) {
                if ((networkRetries || 0) > 0) {
                    await new Promise(function (resolve) { window.setTimeout(resolve, 500); });
                    return requestJson(url, options, networkRetries - 1);
                }
                throw new Error('The connection to the shopping assistant was interrupted. Please try again.');
            }
            const raw = await response.text();
            let json;
            try {
                json = JSON.parse(raw);
            } catch (e) {
                throw new Error('The shopping assistant received an invalid server response. Please try again.');
            }
            if (!response.ok || !json.success) throw new Error(json.message || 'Request failed');
            return json.data || {};
        }

        function refreshStorefrontPrivateData(action) {
            const cartActions = ['add_to_cart','remove_cart_item','update_cart_item','clear_cart','apply_coupon','remove_coupon','set_guest_email','set_checkout_address','set_shipping_method','set_payment_method','confirm_action'];
            const customerActions = ['add_to_wishlist','remove_wishlist_item','subscribe_newsletter','unsubscribe_newsletter','update_customer_profile','save_customer_address','prepare_delete_customer_address'];
            if (cartActions.indexOf(action) === -1 && customerActions.indexOf(action) === -1) return;

            // Hyva consumes this event to refresh private-content sections such as cart/customer.
            try { window.dispatchEvent(new CustomEvent('reload-customer-section-data')); } catch (e) {}

            // Luma/Blank: integrate with Magento_Customer customer-data when RequireJS is available,
            // but never make the assistant itself depend on RequireJS.
            try {
                if (typeof window.require === 'function') {
                    window.require(['Magento_Customer/js/customer-data'], function (customerData) {
                        const sections = cartActions.indexOf(action) !== -1 ? ['cart'] : ['customer','wishlist'];
                        try { customerData.invalidate(sections); customerData.reload(sections, true); } catch (e) {}
                    });
                }
            } catch (e) {}
        }

        async function executeDirect(action, argumentsValue, userLabel) {
            if (!root.dataset.actionEndpoint) { sendMessage(userLabel || action); return; }
            if (send.disabled) return;
            addMessage('user', userLabel || String(action).replace(/_/g,' '), null, true);
            emitAgentEvent('direct-action-start', {action:action});
            send.disabled=true; input.disabled=true; status.textContent='Working…';
            const body=new URLSearchParams(); body.set('form_key',currentFormKey()); body.set('action',action); body.set('arguments',JSON.stringify(argumentsValue||{})); body.set('context',JSON.stringify({client_id:state.client_id,conversation_id:state.conversation_id,cart_id:state.cart_id||null})); const idem=(window.crypto&&typeof window.crypto.randomUUID==='function')?window.crypto.randomUUID():''; if(idem) body.set('idempotency_key',idem);
            try {
                const data=await requestJson(root.dataset.actionEndpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8','X-Requested-With':'XMLHttpRequest'},body:body.toString()});
                state.client_id=data.client_id||state.client_id; state.conversation_id=data.conversation_id||state.conversation_id;
                if(data.cart&&typeof data.cart.cart_id==='string'&&data.cart.cart_id)state.cart_id=data.cart.cart_id;
                addMessage('assistant',data.assistant_message||'Done.',data,true,true); persist(); emitState(); refreshStorefrontPrivateData(action); emitAgentEvent('direct-action-complete',{action:action,response:data});
            } catch(e) { addMessage('assistant',e&&e.message?e.message:'The action could not be completed.',null,true); emitAgentEvent('direct-action-error',{action:action,error:e&&e.message?e.message:'Request failed'}); }
            finally { send.disabled=false; input.disabled=false; status.textContent=''; input.focus(); }
        }

        async function loadConversation(id) {
            if (!id) return false;
            status.textContent = 'Loading conversation…';
            try {
                const url = new URL(root.dataset.historyEndpoint, location.href);
                url.searchParams.set('conversation_id', id);
                url.searchParams.set('client_id', state.client_id);
                const data = await requestJson(url.toString(), {credentials:'same-origin'});
                state.client_id = data.client_id || state.client_id;
                state.conversation_id = data.id || id;
                messages.innerHTML = '';
                const history = Array.isArray(data.messages) ? data.messages : [];
                if (!history.length) showWelcome();
                history.forEach(function (m) { addMessage(m.role, m.content, m, false, false); });
                const lastAssistant = history.slice().reverse().find(function (m) { return m.role === 'assistant'; });
                if (lastAssistant) {
                    renderFilters(lastAssistant.filters || []);
                    state.query_phrase = lastAssistant.query_phrase || '';
                }
                messages.scrollTop = messages.scrollHeight;
                persist(); emitState();
                return true;
            } catch (e) {
                state.conversation_id = '';
                persist();
                showWelcome();
                return false;
            } finally { status.textContent = ''; }
        }

        async function loadHistoryList() {
            historyList.innerHTML = '';
            historyList.appendChild(text('div', 'agentic-commerce__empty', 'Loading…'));
            try {
                const url = new URL(root.dataset.historyEndpoint, location.href);
                url.searchParams.set('client_id', state.client_id);
                url.searchParams.set('limit', '30');
                const data = await requestJson(url.toString(), {credentials:'same-origin'});
                state.client_id = data.client_id || state.client_id;
                persist();
                historyList.innerHTML = '';
                const items = Array.isArray(data.items) ? data.items : [];
                if (!items.length) { historyList.appendChild(text('div', 'agentic-commerce__empty', 'No previous conversations yet.')); return; }
                items.forEach(function (item) {
                    const button = text('button', 'agentic-commerce__history-item', '');
                    button.type = 'button';
                    button.appendChild(text('span', 'agentic-commerce__history-title', item.title || 'Conversation'));
                    const date = item.last_message_at ? new Date(String(item.last_message_at).replace(' ', 'T') + 'Z') : null;
                    button.appendChild(text('span', 'agentic-commerce__history-date', date && !isNaN(date) ? date.toLocaleDateString() : ''));
                    button.addEventListener('click', async function () {
                        await loadConversation(item.id);
                        historyPanel.hidden = true;
                        historyButton.setAttribute('aria-expanded', 'false');
                    });
                    historyList.appendChild(button);
                });
            } catch (e) {
                historyList.innerHTML = '';
                historyList.appendChild(text('div', 'agentic-commerce__empty', e.message || 'Could not load history.'));
            }
        }

        async function startConversation() {
            const body = new URLSearchParams();
            body.set('form_key', currentFormKey());
            body.set('client_id', state.client_id);
            const data = await requestJson(root.dataset.startEndpoint, {
                method:'POST', credentials:'same-origin',
                headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8','X-Requested-With':'XMLHttpRequest'},
                body:body.toString()
            });
            state.client_id = data.client_id || state.client_id;
            state.conversation_id = data.id || '';
            state.filters = [];
            state.query_phrase = '';
            persist();
            renderFilters([]);
            showWelcome();
            emitState();
        }

        async function sendMessage(value) {
            value = String(value || '').trim();
            if (!value || send.disabled) return;
            addMessage('user', value, null, true);
            emitAgentEvent('turn-start', {message: value});
            input.value = '';
            send.disabled = true;
            input.disabled = true;
            status.textContent = 'Working…';
            const body = new URLSearchParams();
            body.set('form_key', currentFormKey());
            body.set('message', value);
            const requestId = (window.crypto && typeof window.crypto.randomUUID === 'function')
                ? window.crypto.randomUUID()
                : randomId();
            if (requestId) body.set('idempotency_key', requestId);
            body.set('context', JSON.stringify({
                client_id: state.client_id,
                conversation_id: state.conversation_id,
                cart_id: state.cart_id || null,
                filters: state.filters,
                query_phrase: state.query_phrase,
                page_url: location.pathname + location.search
            }));
            try {
                const data = await requestJson(root.dataset.endpoint, {
                    method:'POST', credentials:'same-origin',
                    headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8','X-Requested-With':'XMLHttpRequest'},
                    body:body.toString()
                }, 1);
                state.client_id = data.client_id || state.client_id;
                state.conversation_id = data.conversation_id || data.session_id || state.conversation_id;
                state.query_phrase = typeof data.query_phrase === 'string' ? data.query_phrase : state.query_phrase;
                addMessage('assistant', data.message || 'Done.', data, true);
                renderFilters(data.filters || []);
                status.textContent = typeof data.total_count === 'number' && Array.isArray(data.products) && data.products.length ? (data.products.length + ' shown' + (data.total_count > data.products.length ? ' · ' + data.total_count + ' total' : '')) : '';
                persist(); emitState();
                emitAgentEvent('turn-complete', {message: value, response: data});
            } catch (e) {
                emitAgentEvent('turn-error', {message: value, error: e && e.message ? e.message : 'Request failed'});
                addMessage('assistant', e && e.message ? e.message : 'The shopping assistant is temporarily unavailable.', null, true);
                status.textContent = '';
            } finally {
                send.disabled = false;
                input.disabled = false;
                input.focus();
            }
        }

        form.addEventListener('submit', function (event) { event.preventDefault(); sendMessage(input.value); });
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); sendMessage(input.value); }
        });
        reset.addEventListener('click', function () { startConversation().catch(function () { state.conversation_id=''; persist(); showWelcome(); }); });
        historyButton.addEventListener('click', function () {
            historyPanel.hidden = !historyPanel.hidden;
            historyButton.setAttribute('aria-expanded', historyPanel.hidden ? 'false' : 'true');
            if (!historyPanel.hidden) loadHistoryList();
        });
        historyClose.addEventListener('click', function () { historyPanel.hidden = true; historyButton.setAttribute('aria-expanded','false'); });
        root.addEventListener('haerriz:agentic:set-context', function (event) {
            const detail = event.detail || {};
            if (Array.isArray(detail.filters)) state.filters = detail.filters;
            if (typeof detail.query_phrase === 'string') state.query_phrase = detail.query_phrase;
            if (typeof detail.cart_id === 'string') state.cart_id = detail.cart_id;
            persist(); renderFilters(state.filters); emitState();
        });

        persist();
        if (state.conversation_id) loadConversation(state.conversation_id); else showWelcome();
    }

    function boot() { document.querySelectorAll('[data-agentic-commerce]').forEach(bootRoot); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, {once:true}); else boot();
})();
