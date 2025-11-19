function toggleUserMenu(event) {
    // Prevent the click from bubbling up to the document click handler
    if (event && event.stopPropagation) {
        event.stopPropagation();
    }
    const dropdown = document.getElementById('userDropdown');
    if (dropdown) {
        dropdown.classList.toggle('active');
        // Toggle 'open' state on the small button for animation
        const btn = document.querySelector('.avatar-toggle');
        if (btn) btn.classList.toggle('open');
    }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('userDropdown');
    const avatar = document.querySelector('.user-avatar');
    if (dropdown && !dropdown.contains(event.target) && !(avatar && avatar.contains(event.target))) {
        dropdown.classList.remove('active');
    }
});