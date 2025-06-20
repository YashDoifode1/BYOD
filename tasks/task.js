 document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Create a placeholder element for drag and drop
    // const placeholder = document.createElement('div');
    // placeholder.className = 'dragging-placeholder';
    // placeholder.style.height = '100px';
    // placeholder.style.background = 'rgba(0,0,0,0.05)';
    // placeholder.style.borderRadius = '6px';
    // placeholder.style.marginBottom = '12px';
    // placeholder.style.border = '2px dashed #ccc';
    // document.body.appendChild(placeholder);

    // Alert notification function
    function showAlert(message, type = 'success', duration = 3000) {
        const alertPlaceholder = document.getElementById('alertPlaceholder');
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show`;
        alert.role = 'alert';
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        // Remove existing alerts first
        const existingAlerts = alertPlaceholder.querySelectorAll('.alert');
        existingAlerts.forEach(alert => alert.remove());
        
        alertPlaceholder.appendChild(alert);
        
        setTimeout(() => {
            alert.classList.add('fade');
            setTimeout(() => alert.remove(), 150);
        }, duration);
    }

    // Drag and Drop functionality with improved feedback
    let draggedItem = null;
    let originalColumn = null;
    let originalPosition = null;
    let isDragging = false;

    document.addEventListener('dragstart', function(e) {
        if (e.target.classList.contains('task-card')) {
            draggedItem = e.target;
            originalColumn = draggedItem.parentElement;
            originalPosition = Array.from(originalColumn.children).indexOf(draggedItem);
            isDragging = true;
            
            setTimeout(() => {
                e.target.classList.add('dragging');
                e.target.style.opacity = '0.4';
            }, 0);
        }
    });

    document.addEventListener('dragend', function(e) {
        if (e.target.classList.contains('task-card')) {
            isDragging = false;
            e.target.classList.remove('dragging');
            e.target.style.opacity = '1';
            draggedItem = null;
            document.querySelector('.dragging-placeholder')?.remove();
        }
    });

    // Add drag events to each column
    document.querySelectorAll('.kanban-column').forEach(column => {
        column.addEventListener('dragover', function(e) {
            if (!isDragging) return;
            e.preventDefault();
            this.classList.add('dropzone');
            
            const columnContent = this.querySelector('.kanban-column-content');
            const afterElement = getDragAfterElement(columnContent, e.clientY);
            const placeholder = document.querySelector('.dragging-placeholder');
            
            if (afterElement == null) {
                columnContent.appendChild(placeholder);
            } else {
                columnContent.insertBefore(placeholder, afterElement);
            }
        });

        column.addEventListener('dragleave', function() {
            this.classList.remove('dropzone');
            document.querySelector('.dragging-placeholder')?.remove();
        });

        column.addEventListener('drop', function(e) {
            if (!isDragging) return;
            e.preventDefault();
            this.classList.remove('dropzone');
            document.querySelector('.dragging-placeholder')?.remove();
            
            if (draggedItem) {
                const newStatus = this.getAttribute('data-status');
                const taskId = draggedItem.getAttribute('data-task-id');
                const columnContent = this.querySelector('.kanban-column-content');
                const afterElement = getDragAfterElement(columnContent, e.clientY);
                
                // Create a temporary clone for smooth animation
                const clone = draggedItem.cloneNode(true);
                clone.style.transition = 'all 0.3s ease';
                clone.style.transform = 'scale(1.05)';
                clone.style.boxShadow = '0 5px 15px rgba(0,0,0,0.1)';
                clone.style.opacity = '0.8';
                
                // Position the clone where it will end up
                if (afterElement == null) {
                    columnContent.appendChild(clone);
                } else {
                    columnContent.insertBefore(clone, afterElement);
                }
                
                // Hide the original during transition
                draggedItem.style.visibility = 'hidden';
                
                // Animate the clone
                setTimeout(() => {
                    clone.style.transform = 'scale(1)';
                    clone.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';
                    clone.style.opacity = '1';
                }, 10);
                
                // Update the task status via AJAX
                updateTaskStatus(taskId, newStatus)
                    .then(success => {
                        if (success) {
                            // Remove clone and show original in new position
                            clone.remove();
                            draggedItem.style.visibility = 'visible';
                            
                            // Move the actual item to the new column
                            if (afterElement == null) {
                                columnContent.appendChild(draggedItem);
                            } else {
                                columnContent.insertBefore(draggedItem, afterElement);
                            }
                            
                            // Update the status dropdown text
                            updateStatusText(draggedItem, newStatus);
                            showAlert('Task status updated successfully!');
                        } else {
                            // Revert to original position if update failed
                            clone.remove();
                            draggedItem.style.visibility = 'visible';
                            if (originalColumn && originalPosition !== null) {
                                const children = Array.from(originalColumn.children);
                                if (originalPosition >= children.length) {
                                    originalColumn.appendChild(draggedItem);
                                } else {
                                    originalColumn.insertBefore(draggedItem, children[originalPosition]);
                                }
                            }
                            showAlert('Failed to update task status', 'danger');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        clone.remove();
                        draggedItem.style.visibility = 'visible';
                        showAlert('An error occurred while updating task status', 'danger');
                    });
            }
        });
    });

    // Helper function to determine drop position
    function getDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll('.task-card:not(.dragging):not(.dragging-placeholder)')];
        
        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    // Improved status update function with Promise
    function updateTaskStatus(taskId, newStatus) {
        return new Promise((resolve, reject) => {
            fetch('update_task_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `task_id=${taskId}&status=${newStatus}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    resolve(true);
                } else {
                    console.error('Update failed:', data.message);
                    resolve(false);
                }
            })
            .catch(error => {
                console.error('Error updating task status:', error);
                reject(error);
            });
        });
    }

    // Update status text in dropdown
    function updateStatusText(taskCard, newStatus) {
        const dropdownBtn = taskCard.querySelector('.dropdown-toggle');
        const statusText = {
            'todo': 'To Do',
            'in_progress': 'In Progress',
            'done': 'Done'
        };
        dropdownBtn.textContent = statusText[newStatus];
        
        // Also update the active option in dropdown
        const dropdownItems = taskCard.querySelectorAll('.status-option');
        dropdownItems.forEach(item => {
            item.classList.remove('active');
            if (item.getAttribute('data-status') === newStatus) {
                item.classList.add('active');
            }
        });
    }

    // Handle status dropdown changes
    document.addEventListener('click', function(e) {
        // Status dropdown options
        if (e.target.classList.contains('status-option')) {
            e.preventDefault();
            const taskCard = e.target.closest('.task-card');
            const taskId = taskCard.getAttribute('data-task-id');
            const newStatus = e.target.getAttribute('data-status');
            
            // Show loading state
            const dropdownBtn = taskCard.querySelector('.dropdown-toggle');
            const originalText = dropdownBtn.textContent;
            dropdownBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Updating...';
            
            updateTaskStatus(taskId, newStatus)
                .then(success => {
                    if (success) {
                        updateStatusText(taskCard, newStatus);
                        showAlert('Task status updated successfully!');
                    } else {
                        dropdownBtn.textContent = originalText;
                        showAlert('Failed to update task status', 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    dropdownBtn.textContent = originalText;
                    showAlert('An error occurred while updating task status', 'danger');
                });
        }
        
        // Quick status buttons
        if (e.target.classList.contains('quick-status') || 
            e.target.parentElement.classList.contains('quick-status')) {
            e.preventDefault();
            const btn = e.target.classList.contains('quick-status') ? e.target : e.target.parentElement;
            const taskCard = btn.closest('.task-card');
            const taskId = taskCard.getAttribute('data-task-id');
            const newStatus = btn.getAttribute('data-status');
            
            // Show loading state on button
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
            btn.disabled = true;
            
            updateTaskStatus(taskId, newStatus)
                .then(success => {
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                    
                    if (success) {
                        updateStatusText(taskCard, newStatus);
                        
                        // Move to the appropriate column
                        const newColumn = document.querySelector(`.kanban-column[data-status="${newStatus}"] .kanban-column-content`);
                        newColumn.appendChild(taskCard);
                        
                        showAlert('Task status updated successfully!');
                    } else {
                        showAlert('Failed to update task status', 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                    showAlert('An error occurred while updating task status', 'danger');
                });
        }
        
        // Load task details modal
        if (e.target.hasAttribute('data-bs-target') && 
            e.target.getAttribute('data-bs-target') === '#taskDetailsModal') {
            const taskId = e.target.getAttribute('data-task-id');
            loadTaskDetails(taskId);
        }
    });

    // Load task details for modal
    function loadTaskDetails(taskId) {
        const modalContent = document.getElementById('taskDetailsContent');
        modalContent.innerHTML = `
            <div class="text-center my-5">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>`;
            
        
        fetch(`get_task_details.php?task_id=${taskId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(html => {
                modalContent.innerHTML = html;
                // Initialize any date pickers or other JS for the details form
            })
            .catch(error => {
                console.error('Error:', error);
                modalContent.innerHTML = `
                    <div class="alert alert-danger">
                        Error loading task details. Please try again.
                        <button class="btn btn-sm btn-outline-secondary mt-2" onclick="loadTaskDetails(${taskId})">
                            Retry
                        </button>
                    </div>`;
            });
    }

    // Save task details changes
    document.getElementById('saveTaskChanges')?.addEventListener('click', function() {
        const form = document.getElementById('taskDetailsForm');
        if (!form) return;
        
        const saveBtn = this;
        const originalHtml = saveBtn.innerHTML;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
        saveBtn.disabled = true;
        
        const formData = new FormData(form);
        formData.append('task_id', form.dataset.taskId);
        
        fetch('update_task_details.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showAlert('Task updated successfully!');
                // Close modal after 1 second
                setTimeout(() => {
                    bootstrap.Modal.getInstance(document.getElementById('taskDetailsModal')).hide();
                    // Refresh the board to show changes
                    location.reload();
                }, 1000);
            } else {
                saveBtn.innerHTML = originalHtml;
                saveBtn.disabled = false;
                showAlert(data.message || 'Error updating task', 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            saveBtn.innerHTML = originalHtml;
            saveBtn.disabled = false;
            showAlert('An error occurred while updating task', 'danger');
        });
    });

    // Form submission with AJAX for new tasks
    document.getElementById('taskForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const form = this;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalHtml = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
        submitBtn.disabled = true;
        
        const formData = new FormData(form);
        
        fetch('save_task.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            submitBtn.innerHTML = originalHtml;
            submitBtn.disabled = false;
            
            if (data.success) {
                showAlert('Task created successfully!');
                // Close modal and refresh
                bootstrap.Modal.getInstance(document.getElementById('addTaskModal')).hide();
                location.reload();
            } else {
                showAlert(data.message || 'Error creating task', 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            submitBtn.innerHTML = originalHtml;
            submitBtn.disabled = false;
            showAlert('An error occurred while creating task', 'danger');
        });
    });

    // Refresh board button
    document.getElementById('refreshBoard')?.addEventListener('click', function() {
        const btn = this;
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Refreshing...';
        btn.disabled = true;
        
        // Add slight delay so user can see the spinner
        setTimeout(() => {
            location.reload();
        }, 500);
    });

    // Initialize modal events to clear content when closed
    const taskDetailsModal = document.getElementById('taskDetailsModal');
    if (taskDetailsModal) {
        taskDetailsModal.addEventListener('hidden.bs.modal', function () {
            document.getElementById('taskDetailsContent').innerHTML = `
                <div class="text-center my-5">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>`;
        });
    }
});