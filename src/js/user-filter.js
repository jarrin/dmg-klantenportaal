/**
 * User filtering functionality for admin users page
 */

function filterUsers() {
    const searchInput = document.getElementById('userSearch').value.toLowerCase();
    const userRows = document.querySelectorAll('.user-row');
    let visibleCount = 0;

    userRows.forEach(row => {
        const name = row.getAttribute('data-name');
        const email = row.getAttribute('data-email');
        
        // Check if search matches name or email
        if (name.includes(searchInput) || email.includes(searchInput)) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    // Show message if no results
    const tbody = document.querySelector('table tbody');
    let noResultsRow = document.getElementById('noResultsRow');
    
    if (visibleCount === 0 && searchInput !== '') {
        if (!noResultsRow) {
            noResultsRow = document.createElement('tr');
            noResultsRow.id = 'noResultsRow';
            noResultsRow.innerHTML = '<td colspan="7" style="text-align: center; padding: 20px; color: #999;">Geen gebruikers gevonden</td>';
            tbody.appendChild(noResultsRow);
        }
        noResultsRow.style.display = '';
    } else if (noResultsRow) {
        noResultsRow.style.display = 'none';
    }
}
