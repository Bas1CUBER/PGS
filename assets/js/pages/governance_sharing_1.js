document.addEventListener('DOMContentLoaded', function () {
    var cards = document.querySelectorAll('.stat-card');
    var tbody = document.getElementById('uploads-body');
    var rows = tbody ? Array.from(tbody.querySelectorAll('tr')) : [];
    var searchInput = document.getElementById('searchInput');
    var searchFilter = document.getElementById('searchFilter');
    var clearSearch = document.getElementById('clearSearch');
    var visibleCountEl = document.getElementById('visibleCount');
    var totalCountEl = document.getElementById('totalCount');
    var filters = ['all','pdf','image','approved','in_progress','returned'];
    var currentCardFilter = 'all';
    var currentSearchTerm = '';
    var currentSearchField = 'all';

    var dataRows = rows.filter(function(r) { return !r.querySelector('td[colspan]'); });
    totalCountEl.textContent = dataRows.length;

    function getCellText(row, field) {
        var tds = row.querySelectorAll('td');
        if (!tds.length) return '';
        switch(field) {
            case 'details':
                return (tds[0]?.textContent || '').toLowerCase();
            case 'uploaded_by':
                return (tds[1]?.textContent || '').toLowerCase();
            case 'type':
                return (tds[3]?.textContent || '').toLowerCase();
            case 'file':
                return (tds[4]?.textContent || '').toLowerCase();
            case 'status':
                var statusTd = tds[5];
                if (statusTd) {
                    var sel = statusTd.querySelector('select');
                    if (sel) return sel.value.toLowerCase();
                    return (statusTd.textContent || '').toLowerCase();
                }
                return '';
            default:
                return row.textContent.toLowerCase();
        }
    }

    function applyFilters() {
        var visibleCount = 0;
        rows.forEach(function(row) {
            if (row.querySelector('td[colspan]')) {
                row.style.display = (dataRows.length === 0 || (currentCardFilter === 'all' && currentSearchTerm === '')) ? '' : 'none';
                return;
            }

            var dt = row.dataset.docType || '';
            var st = row.dataset.status || '';
            var showByCard = true;
            var showBySearch = true;

            if (currentCardFilter === 'pdf') showByCard = dt === 'pdf';
            else if (currentCardFilter === 'image') showByCard = dt === 'image';
            else if (currentCardFilter === 'approved') showByCard = st === 'approved';
            else if (currentCardFilter === 'in_progress') showByCard = st === 'in_progress';
            else if (currentCardFilter === 'returned') showByCard = st === 'returned';

            if (currentSearchTerm) {
                var searchText = currentSearchField === 'all' 
                    ? row.textContent.toLowerCase() 
                    : getCellText(row, currentSearchField);
                showBySearch = searchText.indexOf(currentSearchTerm) > -1;
            }

            var show = showByCard && showBySearch;
            row.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        });
        visibleCountEl.textContent = visibleCount;
    }

    cards.forEach(function(card, idx) {
        card.style.cursor = 'pointer';
        card.addEventListener('click', function() {
            cards.forEach(function(c){ c.classList.remove('active'); });
            card.classList.add('active');
            currentCardFilter = filters[idx] || 'all';
            applyFilters();
        });
    });

    searchInput.addEventListener('input', function() {
        currentSearchTerm = this.value.toLowerCase().trim();
        applyFilters();
    });

    searchFilter.addEventListener('change', function() {
        currentSearchField = this.value;
        applyFilters();
    });

    clearSearch.addEventListener('click', function() {
        searchInput.value = '';
        currentSearchTerm = '';
        applyFilters();
    });

    document.querySelectorAll('select[name="status"]').forEach(function(sel){
        sel.addEventListener('change', function(){
            var tr = sel.closest('tr');
            if (tr) {
                tr.dataset.status = sel.value.toLowerCase().replace(' ', '_');
                applyFilters();
            }
        });
    });

    applyFilters();
});
