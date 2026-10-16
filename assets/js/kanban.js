// kanban.js - Kanban board functionality

class KanbanBoard {
    constructor() {
        this.draggedTask = null;
        this.dragOverColumn = null;
        this.columns = ['todo', 'in_progress', 'done', 'approved', 'blocked'];
    }
    
    init() {
        this.initDragAndDrop();
        this.initFilters();
        this.initTaskActions();
        this.setupAutoRefresh();
    }
    
    initDragAndDrop() {
        const taskCards = document.querySelectorAll('.task-card');
        const columns = document.querySelectorAll('.task-list');
        
        taskCards.forEach(card => {
            card.addEventListener('dragstart', this.handleDragStart.bind(this));
            card.addEventListener('dragend', this.handleDragEnd.bind(this));
        });
        
        columns.forEach(column => {
            column.addEventListener('dragover', this.handleDragOver.bind(this));
            column.addEventListener('dragenter', this.handleDragEnter.bind(this));
            column.addEventListener('dragleave', this.handleDragLeave.bind(this));
            column.addEventListener('drop', this.handleDrop.bind(this));
        });
    }
    
    handleDragStart(e) {
        this.draggedTask = e.target;
        this.draggedTask.classList.add('dragging');
        e.dataTransfer.setData('text/plain', this.draggedTask.dataset.taskId);
        e.dataTransfer.effectAllowed = 'move';
    }
    
    handleDragEnd(e) {
        this.draggedTask.classList.remove('dragging');
        document.querySelectorAll('.task-list').forEach(list => {
            list.classList.remove('drag-over');
        });
    }
    
    handleDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    }
    
    handleDragEnter(e) {
        e.preventDefault();
        const column = e.target.closest('.task-list');
        if (column && !column.classList.contains('drag-over')) {
            column.classList.add('drag-over');
            this.dragOverColumn = column;
        }
    }
    
    handleDragLeave(e) {
        const column = e.target.closest('.task-list');
        if (column && column === this.dragOverColumn) {
            column.classList.remove('drag-over');
            this.dragOverColumn = null;
        }
    }
    
    async handleDrop(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const column = e.target.closest('.task-list');
        if (!column || !this.draggedTask) return;
        
        const taskId = this.draggedTask.dataset.taskId;
        const newStatus = column.closest('.kanban-column').dataset.status;
        const oldStatus = this.draggedTask.dataset.status;
        
        if (newStatus === oldStatus) {
            column.classList.remove('drag-over');
            return;
        }
        
        try {
            const response = await this.updateTaskStatus(taskId, newStatus);
            
            if (response.success) {
                // Move task to new column
                column.appendChild(this.draggedTask);
                this.draggedTask.dataset.status = newStatus;
                
                // Update task count badges
                this.updateColumnCounts();
                
                // Show success message
                this.showToast('Task status updated successfully!', 'success');
                
                // If moving to "done", check for AI breakdown
                if (newStatus === 'done') {
                    // this.checkForAIRecommendations(taskId);
                }
            } else {
                throw new Error(response.message);
            }
        } catch (error) {
            console.error('Error updating task:', error);
            this.showToast('Error updating task: ' + error.message, 'error');
        } finally {
            column.classList.remove('drag-over');
        }
    }
    
    async updateTaskStatus(taskId, newStatus) {
        const response = await fetch('ajax/update_task_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `task_id=${taskId}&status=${newStatus}`
        });
        
        return await response.json();
    }
    
    updateColumnCounts() {
        this.columns.forEach(status => {
            const column = document.querySelector(`.kanban-column[data-status="${status}"]`);
            if (column) {
                const taskCount = column.querySelectorAll('.task-card').length;
                const countBadge = column.querySelector('.task-count');
                if (countBadge) {
                    countBadge.textContent = taskCount;
                }
            }
        });
    }
    
    initFilters() {
        const projectFilter = document.getElementById('project-filter');
        const priorityFilter = document.getElementById('priority-filter');
        
        if (projectFilter) {
            projectFilter.addEventListener('change', this.filterTasks.bind(this));
        }
        
        if (priorityFilter) {
            priorityFilter.addEventListener('change', this.filterTasks.bind(this));
        }
    }
    
    filterTasks() {
        const projectFilter = document.getElementById('project-filter');
        const priorityFilter = document.getElementById('priority-filter');
        
        const selectedProject = projectFilter ? projectFilter.value : '';
        const selectedPriority = priorityFilter ? priorityFilter.value : '';
        
        document.querySelectorAll('.task-card').forEach(card => {
            let show = true;
            
            if (selectedProject) {
                const projectId = card.dataset.projectId || '';
                show = show && projectId === selectedProject;
            }
            
            if (selectedPriority) {
                const priority = card.querySelector('.task-priority')?.textContent.toLowerCase() || '';
                show = show && priority === selectedPriority;
            }
            
            card.style.display = show ? 'block' : 'none';
        });
    }
    
    initTaskActions() {
        // Edit task buttons
        document.querySelectorAll('.task-action-btn .fa-edit').forEach(btn => {
            btn.closest('.task-action-btn').addEventListener('click', (e) => {
                e.stopPropagation();
                const taskCard = btn.closest('.task-card');
                this.editTask(taskCard.dataset.taskId);
            });
        });
        

    }
    
    async editTask(taskId) {
        try {
            const response = await fetch(`ajax/get_task.php?id=${taskId}`);
            const data = await response.json();
            
            if (data.success && data.task) {
                const task = data.task;
                // Populate modal with task data
                document.getElementById('modalTitle').textContent = 'Edit Task';
                document.getElementById('task_id').value = task.task_id;
                document.getElementById('task_name').value = task.title || task.task_name || '';
                document.getElementById('description').value = task.description || '';
                document.getElementById('project_id').value = task.project_id;
                document.getElementById('priority').value = task.priority;
                document.getElementById('assigned_to').value = task.assigned_to || '';
                document.getElementById('start_date').value = task.start_date || '';
                document.getElementById('due_date').value = task.due_date;
                
                // Load skills
                const skillsContainer = document.getElementById('skillsContainer');
                skillsContainer.innerHTML = '';
                if (task.required_skills) {
                    task.required_skills.forEach(skill => {
                        const skillTag = document.createElement('div');
                        skillTag.className = 'skill-tag';
                        skillTag.innerHTML = `
                            ${skill.skill_name}
                            <button type="button" class="remove-skill" data-skill-id="${skill.skill_id}">&times;</button>
                        `;
                        skillsContainer.appendChild(skillTag);
                    });
                }
                
                // Show modal
                document.getElementById('taskModal').style.display = 'flex';
            }
        } catch (error) {
            console.error('Error loading task:', error);
            this.showToast('Error loading task details', 'error');
        }
    }
    

    showModal(title, content) {
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3>${title}</h3>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    ${content}
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary modal-close">Close</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Add close handlers
        modal.querySelectorAll('.modal-close').forEach(btn => {
            btn.addEventListener('click', () => modal.remove());
        });
        
        // Close when clicking outside
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        });
    }
    
    showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        toast.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: ${type === 'success' ? '#4CAF50' : type === 'error' ? '#f44336' : '#2196F3'};
            color: white;
            padding: 12px 24px;
            border-radius: 4px;
            z-index: 1000;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            animation: slideIn 0.3s ease;
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
        
        // Add CSS for animations
        if (!document.querySelector('#toast-animations')) {
            const style = document.createElement('style');
            style.id = 'toast-animations';
            style.textContent = `
                @keyframes slideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes slideOut {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(100%); opacity: 0; }
                }
            `;
            document.head.appendChild(style);
        }
    }
    


    
    setupAutoRefresh() {
        // Refresh every 30 seconds to get updates
        setInterval(() => {
            if (document.visibilityState === 'visible') {
                this.refreshBoard();
            }
        }, 3000000); // 5 minutes
    }
    
    async refreshBoard() {
        try {
            const response = await fetch('ajax/get_tasks.php');
            const tasks = await response.json();
            
            // Update board with new tasks
            this.updateBoard(tasks);
        } catch (error) {
            console.error('Error refreshing board:', error);
        }
    }
    
    updateBoard(tasks) {
        // Clear all columns
        this.columns.forEach(status => {
            const column = document.querySelector(`#${status}-list`);
            if (column) {
                column.innerHTML = '';
            }
        });
        
        // Add tasks to columns
        tasks.forEach(task => {
            const column = document.querySelector(`#${task.status}-list`);
            if (column) {
                column.appendChild(this.createTaskCard(task));
            }
        });
        
        // Update counts
        this.updateColumnCounts();
    }
    
    createTaskCard(task) {
        const card = document.createElement('div');
        card.className = 'task-card';
        card.dataset.taskId = task.task_id;
        card.dataset.status = task.status;
        card.draggable = true;
        
        // Build card HTML based on task data
        card.innerHTML = `
            <div class="task-header">
                <div class="task-title">${task.task_name}</div>
                <div class="task-actions">
                    <button class="task-action-btn" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>
            </div>
            <div class="task-meta">
                <span>${task.project_name}</span>
                <span class="task-priority priority-${task.priority}">${task.priority}</span>
            </div>
        `;
        
        return card;
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.kanbanBoard = new KanbanBoard();
    window.kanbanBoard.init();
});