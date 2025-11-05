/**
 * Product filtering functionality for admin products page
 */

function filterRows(rowClass, searchValue) {
    const rows = document.querySelectorAll('.' + rowClass);
    const searchLower = searchValue.toLowerCase();
    let visibleCount = 0;

    rows.forEach(row => {
        const searchData = row.getAttribute('data-search') || '';
        if (searchData.includes(searchLower)) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    // Show no results message if needed
    const tbody = document.querySelector('table tbody');
    if (tbody && visibleCount === 0 && searchValue !== '') {
        let noResultsRow = tbody.querySelector('.no-results-row');
        if (!noResultsRow) {
            noResultsRow = document.createElement('tr');
            noResultsRow.className = 'no-results-row';
            noResultsRow.innerHTML = '<td colspan="10" style="text-align: center; padding: 20px; color: #999;">Geen resultaten gevonden</td>';
            tbody.appendChild(noResultsRow);
        }
        noResultsRow.style.display = '';
    } else {
        const noResultsRow = tbody?.querySelector('.no-results-row');
        if (noResultsRow) {
            noResultsRow.style.display = 'none';
        }
    }
}
