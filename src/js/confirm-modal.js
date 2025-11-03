/**
 * Confirmation Modal JavaScript Component
 * Shared modal functionality for critical actions
 */

(function() {
    let confirmAction = null;
    let confirmType = 'default';

    window.ConfirmModal = {
        /**
         * Open confirmation modal
         * @param {string} title - Modal title
         * @param {string} message - Modal message
         * @param {string} action - URL to redirect to on confirm, or callback function
         * @param {string} type - Modal type: 'default' or 'danger'
         */
        open: function(title, message, action, type = 'default') {
            let modal = document.getElementById('confirmModal');
            
            // Create modal if it doesn't exist
            if (!modal) {
                modal = this._createModal();
            }
            
            document.getElementById('confirmTitle').textContent = title;
            document.getElementById('confirmMessage').textContent = message;
            confirmAction = action;
            confirmType = type;
            
            modal.className = 'confirm-modal active';
            if (type === 'danger') {
                modal.classList.add('danger');
            }
            
            // Focus on confirm button for accessibility
            document.getElementById('confirmButton').focus();
        },

        close: function() {
            const modal = document.getElementById('confirmModal');
            if (modal) {
                modal.classList.remove('active', 'danger');
            }
            confirmAction = null;
            confirmType = 'default';
        },

        confirm: function() {
            if (confirmAction) {
                if (typeof confirmAction === 'function') {
                    confirmAction();
                } else if (typeof confirmAction === 'string') {
                    window.location.href = confirmAction;
                }
            }
            this.close();
        },

        _createModal: function() {
            const html = `
                <div id="confirmModal" class="confirm-modal">
                    <div class="confirm-modal-content">
                        <h2 id="confirmTitle" class="confirm-modal-header"></h2>
                        <p id="confirmMessage" class="confirm-modal-body"></p>
                        <div class="confirm-modal-footer">
                            <button onclick="ConfirmModal.close()" class="btn btn-secondary" id="confirmButtonCancel">
                                Annuleren
                            </button>
                            <button onclick="ConfirmModal.confirm()" class="btn confirm-btn-confirm" id="confirmButton">
                                Bevestigen
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', html);
            
            const modal = document.getElementById('confirmModal');
            
            // Close modal on outside click
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    ConfirmModal.close();
                }
            });
            
            // Close on ESC key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && modal.classList.contains('active')) {
                    ConfirmModal.close();
                }
            });
            
            return modal;
        }
    };

    // Convenience function for onclick handlers
    window.openConfirmModal = function(title, message, action, type = 'default') {
        ConfirmModal.open(title, message, action, type);
        return false;
    };
})();
