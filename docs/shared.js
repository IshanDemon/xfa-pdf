// ============================================================
// Shared layout components for XFA-PDF documentation
// ============================================================

var DOCS_NAV = [
    { title: 'Getting Started', items: [
        { label: 'Introduction', href: 'index.html' },
        { label: 'Installation', href: 'installation.html' },
        { label: 'Configuration', href: 'configuration.html' },
        { label: 'Quick Start', href: 'quick-start.html' },
    ]},
    { title: 'API Reference', items: [
        { label: 'Facade API', href: 'facade-api.html' },
        { label: 'Section Proxy', href: 'facade-api.html#section-proxy', parent: 'Facade API' },
        { label: 'Manager Internal API', href: 'facade-api.html#manager-internal', parent: 'Facade API' },
        { label: 'XfaPdf Value Object', href: 'facade-api.html#value-object', parent: 'Facade API' },
    ]},
    { title: 'Services', items: [
        { label: 'PdfBinaryService', href: 'services.html#pdf-binary-service' },
        { label: 'DatasetService', href: 'services.html#dataset-service' },
        { label: 'TemplateService', href: 'services.html#template-service' },
        { label: 'RepeatableService', href: 'services.html#repeatable-service' },
        { label: 'PreviewService', href: 'services.html#preview-service' },
        { label: 'NamespaceService', href: 'services.html#namespace-service' },
    ]},
    { title: 'Features', items: [
        { label: 'Web UI Guide', href: 'web-ui.html' },
        { label: 'CLI Commands', href: 'cli.html' },
        { label: 'Repeatable Items', href: 'repeatables.html' },
        { label: 'Exceptions', href: 'exceptions.html' },
    ]},
    { title: 'Section Guides', items: [
        { label: '3. Personal Details', href: 'sections/personal-details.html' },
        { label: '4. Scope of Work', href: 'sections/scope-of-work.html' },
        { label: '5. Previous Appraisals', href: 'sections/previous-appraisals.html' },
        { label: '6. Last Year\'s PDP', href: 'sections/last-years-pdp.html' },
        { label: '7. CPD', href: 'sections/cpd.html' },
        { label: '8. Quality Improvement', href: 'sections/quality-improvement.html' },
        { label: '9. Significant Events', href: 'sections/significant-events.html' },
        { label: '10. Feedback', href: 'sections/feedback.html' },
        { label: '11. Complaints', href: 'sections/complaints.html' },
        { label: '12. Achievements', href: 'sections/achievements.html' },
        { label: '13. Probity & Health', href: 'sections/probity.html' },
        { label: '14. Additional Info', href: 'sections/additional-info.html' },
        { label: '15. Supporting Info', href: 'sections/supporting-information.html' },
        { label: '16. Pre-Appraisal Prep', href: 'sections/pre-appraisal-prep.html' },
        { label: '17. Checklist', href: 'sections/checklist.html' },
        { label: '18. Agreed PDP', href: 'sections/agreed-pdp.html' },
        { label: '19. Appraisal Summary', href: 'sections/appraisal-summary.html' },
        { label: '20. Appraisal Outputs', href: 'sections/appraisal-outputs.html' },
    ]},
    { title: 'Reference', items: [
        { label: 'XFA Technical Reference', href: 'xfa-reference.html' },
        { label: 'Troubleshooting', href: 'troubleshooting.html' },
    ]},
];

function getBasePath() {
    // Detect if we're in a subdirectory (e.g. sections/)
    var path = window.location.pathname;
    if (path.indexOf('/sections/') !== -1) return '../';
    return '';
}

function getCurrentPage() {
    var path = window.location.pathname;
    // Return path relative to docs root, e.g. "index.html" or "sections/scope-of-work.html"
    var docsIdx = path.indexOf('/docs/');
    if (docsIdx !== -1) return path.substring(docsIdx + 6) || 'index.html';
    var file = path.substring(path.lastIndexOf('/') + 1) || 'index.html';
    return file;
}

function isActiveLink(href) {
    var currentPage = getCurrentPage();
    var linkPage = href.split('#')[0] || 'index.html';
    return currentPage === linkPage;
}

function renderSidebar() {
    var currentPage = getCurrentPage();
    var base = getBasePath();
    var html = '<div class="py-4 px-2">';

    DOCS_NAV.forEach(function(group) {
        html += '<p class="px-3 mt-4 mb-2 text-xs font-semibold text-gray-400 uppercase tracking-wider first:mt-0">' + group.title + '</p>';
        group.items.forEach(function(item) {
            var isActive = isActiveLink(item.href);
            var isSub = !!item.parent;
            var classes = 'block py-1.5 text-sm rounded transition-colors duration-150';
            if (isSub) {
                classes += ' pl-8 pr-3';
                classes += isActive ? ' text-blue-600 font-medium' : ' text-gray-500 hover:text-gray-700';
            } else {
                classes += ' px-3';
                classes += isActive
                    ? ' bg-blue-50 text-blue-700 font-semibold border-l-3 border-blue-600'
                    : ' text-gray-600 hover:bg-gray-50 hover:text-gray-900 border-l-3 border-transparent';
            }
            html += '<a href="' + base + item.href + '" class="' + classes + '">' + item.label + '</a>';
        });
    });

    html += '</div>';
    return html;
}

function renderTopBar() {
    return '' +
        '<header class="fixed top-0 left-0 right-0 h-14 bg-white border-b border-gray-200 z-40 flex items-center px-4 lg:px-6">' +
            '<button id="menu-toggle" class="lg:hidden mr-3 p-1.5 rounded hover:bg-gray-100" aria-label="Toggle menu">' +
                '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>' +
                '</svg>' +
            '</button>' +
            '<a href="' + getBasePath() + 'index.html" class="flex items-center gap-2 no-underline">' +
                '<span class="text-lg font-bold text-gray-900">xfa/pdf</span>' +
                '<span class="text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full font-medium">v1.0</span>' +
            '</a>' +
            '<div class="ml-auto relative" id="search-container">' +
                '<div class="relative">' +
                    '<svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>' +
                    '</svg>' +
                    '<input id="search-input" type="text" placeholder="Search... (Ctrl+K)" ' +
                        'class="w-40 md:w-56 pl-8 pr-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">' +
                '</div>' +
                '<div id="search-results" class="absolute right-0 top-full mt-1 w-72 bg-white border border-gray-200 rounded-lg shadow-lg overflow-y-auto hidden" style="max-height:20rem"></div>' +
            '</div>' +
        '</header>';
}

function initLayout() {
    // Insert top bar
    var topBar = document.createElement('div');
    topBar.innerHTML = renderTopBar();
    document.body.insertBefore(topBar.firstElementChild, document.body.firstChild);

    // Insert overlay
    var overlay = document.createElement('div');
    overlay.id = 'sidebar-overlay';
    overlay.className = 'fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden hidden';
    document.body.appendChild(overlay);

    // Insert sidebar
    var nav = document.createElement('nav');
    nav.id = 'sidebar';
    nav.className = 'fixed top-14 left-0 bottom-0 w-56 bg-white border-r border-gray-200 overflow-y-auto z-50 transform -translate-x-full lg:translate-x-0 transition-transform duration-200';
    nav.innerHTML = renderSidebar();
    document.body.appendChild(nav);

    // Mobile menu toggle
    document.getElementById('menu-toggle').addEventListener('click', function() {
        nav.classList.toggle('-translate-x-full');
        overlay.classList.toggle('hidden');
    });
    overlay.addEventListener('click', function() {
        nav.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
    });

    // Keyboard shortcut
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            document.getElementById('search-input').focus();
        }
        if (e.key === 'Escape') {
            document.getElementById('search-results').classList.add('hidden');
            document.getElementById('search-input').blur();
        }
    });

    initSearch();
}

// ============================================================
// Search across pages using search-index.json
// ============================================================
function initSearch() {
    var input = document.getElementById('search-input');
    var results = document.getElementById('search-results');
    var searchIndex = null;

    // Load search index
    fetch(getBasePath() + 'search-index.json')
        .then(function(r) { return r.json(); })
        .then(function(data) { searchIndex = data; })
        .catch(function() { searchIndex = []; });

    input.addEventListener('input', function() {
        if (!searchIndex) return;
        var query = this.value.toLowerCase().trim();
        if (query.length < 2) {
            results.classList.add('hidden');
            return;
        }

        var matches = searchIndex.filter(function(item) {
            return item.title.toLowerCase().indexOf(query) !== -1 ||
                   item.text.toLowerCase().indexOf(query) !== -1;
        }).slice(0, 8);

        if (matches.length === 0) {
            results.innerHTML = '<div class="p-3 text-sm text-gray-500">No results found</div>';
        } else {
            results.innerHTML = matches.map(function(item) {
                var idx = item.text.toLowerCase().indexOf(query);
                var ctx = '';
                if (idx !== -1) {
                    var s = Math.max(0, idx - 30);
                    var e = Math.min(item.text.length, idx + query.length + 30);
                    ctx = (s > 0 ? '...' : '') + item.text.substring(s, e) + (e < item.text.length ? '...' : '');
                }
                return '<a href="' + getBasePath() + item.href + '" class="block px-3 py-2 border-b border-gray-100 hover:bg-gray-50 no-underline">' +
                    '<div class="text-sm font-medium text-gray-900">' + item.title + '</div>' +
                    '<div class="text-xs text-gray-400">' + item.page + '</div>' +
                    (ctx ? '<div class="text-xs text-gray-500 mt-0.5">' + ctx + '</div>' : '') +
                '</a>';
            }).join('');
        }
        results.classList.remove('hidden');
    });

    document.addEventListener('click', function(e) {
        if (!document.getElementById('search-container').contains(e.target)) {
            results.classList.add('hidden');
        }
    });
}

// ============================================================
// Copy to clipboard for code blocks
// ============================================================
function copyCode(btn) {
    var pre = btn.parentElement.querySelector('pre');
    var text = pre.textContent || pre.innerText;
    navigator.clipboard.writeText(text).then(function() {
        btn.textContent = 'Copied!';
        btn.classList.add('copied');
        setTimeout(function() {
            btn.textContent = 'Copy';
            btn.classList.remove('copied');
        }, 2000);
    });
}

// Auto-init on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLayout);
} else {
    initLayout();
}
