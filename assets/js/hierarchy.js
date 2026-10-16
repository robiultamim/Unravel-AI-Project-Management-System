// hierarchy.js - Hierarchy management functionality

class HierarchyManager {
    constructor() {
        this.chartData = [];
        this.currentProjectId = null;
    }
    
    init() {
        this.loadOrganizationChart();
        this.initEventListeners();
        this.initDragAndDrop();
        // this.initSearch();
    }
    
    async loadOrganizationChart() {
        try {
            const response = await fetch('/unravel/ajax/get_organization_data.php');
            const data = await response.json();
            
            this.chartData = data;
            this.renderOrganizationChart();
        } catch (error) {
            console.error('Error loading organization data:', error);
            // this.showError('Failed to load organization chart');
        }
    }
    
    renderOrganizationChart() {
        const container = document.getElementById('orgChart');
        if (!container) return;
        
        container.innerHTML = '';
        
        // Create admin node (center top)
        const admin = this.chartData.admin;
        if (admin) {
            const adminNode = this.createOrgNode(admin, 'admin', 0);
            adminNode.style.top = '25px';
            adminNode.style.left = '50%';
            adminNode.style.transform = 'translateX(-50%)';
            container.appendChild(adminNode);
        }
        
        // Group managers by admin
        const managers = this.chartData.managers || [];
        const managerRowTop = 150;
        const managerSpacing = 250;
        
        managers.forEach((manager, index) => {
            const leftPosition = 50 + (index - (managers.length - 1) / 2) * managerSpacing;
            const managerNode = this.createOrgNode(manager, 'manager', index + 1);
            managerNode.style.top = managerRowTop + 'px';
            managerNode.style.left = '50%';
            managerNode.style.marginLeft = (leftPosition - 90) + 'px';
            container.appendChild(managerNode);
            
            // Draw connection from admin to manager
            if (admin) {
                const containerWidth = container.offsetWidth || 1200;
                this.drawConnection(
                    containerWidth / 2, 85, // Admin bottom center
                    containerWidth / 2 + leftPosition, managerRowTop // Manager top center
                );
            }
            
            // Add team leaders and employees under each manager
            this.renderTeamStructure(manager, leftPosition, managerRowTop + 150, container);
        });
        
        // Adjust container height
        const maxHeight = Math.max(container.scrollHeight, 500);
        container.style.minHeight = maxHeight + 'px';
    }
    
    renderTeamStructure(manager, managerLeft, startTop, container) {
        const teamLeaders = manager.team_leaders || [];
        const leaderSpacing = 180;
        
        teamLeaders.forEach((leader, leaderIndex) => {
            const leaderLeft = managerLeft - 90 + (leaderIndex * leaderSpacing);
            const leaderNode = this.createOrgNode(leader, 'team_leader', leaderIndex + 101);
            leaderNode.style.top = startTop + 'px';
            leaderNode.style.left = '50%';
            leaderNode.style.marginLeft = (leaderLeft - 90) + 'px';
            container.appendChild(leaderNode);
            
            // Draw connection from manager to team leader
            const containerWidth = container.offsetWidth || 1200;
            this.drawConnection(
                containerWidth / 2 + managerLeft, startTop - 20, // Manager bottom center
                containerWidth / 2 + leaderLeft, startTop // Leader top center
            );
            
            // Add employees under team leader
            this.renderEmployees(leader, leaderLeft, startTop + 125, container);
        });
    }
    
    renderEmployees(leader, leaderLeft, startTop, container) {
        const employees = leader.employees || [];
        const employeeSpacing = 140;
        
        employees.forEach((employee, empIndex) => {
            const empLeft = leaderLeft - 70 + (empIndex * employeeSpacing);
            const empNode = this.createOrgNode(employee, 'employee', empIndex + 201);
            empNode.style.top = startTop + 'px';
            empNode.style.left = '50%';
            empNode.style.marginLeft = (empLeft - 70) + 'px';
            container.appendChild(empNode);
            
            // Draw connection from team leader to employee
            const containerWidth = container.offsetWidth || 1200;
            this.drawConnection(
                containerWidth / 2 + leaderLeft, startTop - 20, // Leader bottom center
                containerWidth / 2 + empLeft, startTop // Employee top center
            );
        });
    }
    
    createOrgNode(user, role, id) {
        const node = document.createElement('div');
        node.className = `org-node org-node-${id} ${role}`;
        node.dataset.userId = user.user_id;
        node.dataset.role = role;
        
        let roleDisplay = role.charAt(0).toUpperCase() + role.slice(1);
        if (role === 'team_leader') {
            roleDisplay = 'Team Leader';
        }
        
        node.innerHTML = `
            <div class="org-node-title">${roleDisplay}</div>
            <div class="org-node-name">${user.first_name} ${user.last_name}</div>
            ${user.department ? `<div class="org-node-meta">${user.department}</div>` : ''}
        `;
        
        // Add click event
        node.addEventListener('click', () => {
            this.showUserDetails(user.user_id);
        });
        
        return node;
    }
    
    drawConnection(x1, y1, x2, y2) {
        const container = document.getElementById('orgChart');
        const svgNS = "http://www.w3.org/2000/svg";
        
        // Create SVG element if it doesn't exist
        let svg = container.querySelector('svg');
        if (!svg) {
            svg = document.createElementNS(svgNS, "svg");
            svg.style.position = 'absolute';
            svg.style.top = '0';
            svg.style.left = '0';
            svg.style.width = '100%';
            svg.style.height = '100%';
            svg.style.pointerEvents = 'none';
            svg.style.zIndex = '1';
            container.appendChild(svg);
        }
        
        // Create line
        const line = document.createElementNS(svgNS, "line");
        line.setAttribute('x1', x1);
        line.setAttribute('y1', y1);
        line.setAttribute('x2', x2);
        line.setAttribute('y2', y2);
        line.setAttribute('stroke', '#dee2e6');
        line.setAttribute('stroke-width', '2');
        line.setAttribute('stroke-dasharray', '5,5');
        
        svg.appendChild(line);
    }
    
    async showUserDetails(userId) {
        try {
            const response = await fetch(`/unravel/ajax/get_user_details.php?id=${userId}`);
            const user = await response.json();
            
            const roleColors = {
                'admin': '#4361ee',
                'manager': '#f72585',
                'team_leader': '#4cc9f0',
                'employee': '#6c757d'
            };
            
            const roleColor = roleColors[user.role] || '#6c757d';
            const roleDisplay = user.role === 'team_leader' ? 'Team Leader' : 
                               user.role.charAt(0).toUpperCase() + user.role.slice(1);
            
            const modalContent = `
                <div class="user-details-card">
                    <!-- Profile Header -->
                    <div class="user-profile-header" style="background: linear-gradient(135deg, ${roleColor}, ${roleColor}dd);">
                        <div class="user-avatar-wrapper">
                            <div class="user-avatar-large" style="background: white; color: ${roleColor};">
                                ${user.first_name.charAt(0)}${user.last_name.charAt(0)}
                            </div>
                            <span class="user-status-dot ${user.is_active ? 'active' : 'inactive'}"></span>
                        </div>
                        <h2 class="user-fullname">${user.first_name} ${user.last_name}</h2>
                        <span class="user-role-badge">${roleDisplay}</span>
                    </div>
                    
                    <!-- User Info Grid -->
                    <div class="user-info-grid">
                        <div class="user-info-item">
                            <div class="info-icon"><i class="fas fa-envelope"></i></div>
                            <div class="info-content">
                                <span class="info-label">Email</span>
                                <span class="info-value">${user.email}</span>
                            </div>
                        </div>
                        <div class="user-info-item">
                            <div class="info-icon"><i class="fas fa-building"></i></div>
                            <div class="info-content">
                                <span class="info-label">Department</span>
                                <span class="info-value">${user.department || 'Not Assigned'}</span>
                            </div>
                        </div>
                        <div class="user-info-item">
                            <div class="info-icon"><i class="fas fa-phone"></i></div>
                            <div class="info-content">
                                <span class="info-label">Phone</span>
                                <span class="info-value">${user.phone_number || 'Not Provided'}</span>
                            </div>
                        </div>
                        <div class="user-info-item">
                            <div class="info-icon"><i class="fas fa-calendar-alt"></i></div>
                            <div class="info-content">
                                <span class="info-label">Member Since</span>
                                <span class="info-value">${new Date(user.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Projects Section -->
                    <div class="user-projects-section">
                        <div class="section-header">
                            <i class="fas fa-folder-open"></i>
                            <h4>Assigned Projects</h4>
                            <span class="project-count">${user.projects ? user.projects.length : 0}</span>
                        </div>
                        <div class="projects-list">
                            ${user.projects && user.projects.length > 0 ? 
                                user.projects.map(project => `
                                    <div class="project-item-card">
                                        <div class="project-icon"><i class="fas fa-project-diagram"></i></div>
                                        <div class="project-details">
                                            <span class="project-name">${project.project_name}</span>
                                            <span class="project-role-tag">${project.role_in_project.replace('_', ' ')}</span>
                                        </div>
                                    </div>
                                `).join('') :
                                '<div class="no-projects"><i class="fas fa-inbox"></i><p>No projects assigned</p></div>'
                            }
                        </div>
                    </div>
                </div>
            `;
            
            this.showModal('', modalContent, true);
        } catch (error) {
            console.error('Error loading user details:', error);
            this.showError('Failed to load user details');
        }
    }
    
    initEventListeners() {
        // Project search
        const searchInput = document.getElementById('projectSearch');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.filterProjects(e.target.value);
            });
        }
        
        // Refresh button
        const refreshBtn = document.querySelector('.btn-refresh');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                this.loadOrganizationChart();
                this.showToast('Chart refreshed', 'success');
            });
        }
        
        // Export button
        const exportBtn = document.querySelector('.btn-export');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => {
                this.exportOrganization();
            });
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.key === 'r' && e.ctrlKey) {
                e.preventDefault();
                this.loadOrganizationChart();
            }
            if (e.key === 'Escape') {
                this.closeAllModals();
            }
        });
    }
    
    initDragAndDrop() {
        // Make organization nodes draggable
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                const nodes = document.querySelectorAll('.org-node');
                nodes.forEach(node => {
                    node.setAttribute('draggable', 'true');
                    
                    node.addEventListener('dragstart', (e) => {
                        e.dataTransfer.setData('text/plain', node.dataset.userId);
                        node.classList.add('dragging');
                    });
                    
                    node.addEventListener('dragend', () => {
                        node.classList.remove('dragging');
                    });
                });
                
                const chart = document.getElementById('orgChart');
                if (chart) {
                    chart.addEventListener('dragover', (e) => {
                        e.preventDefault();
                    });
                    
                    chart.addEventListener('drop', (e) => {
                        e.preventDefault();
                        const userId = e.dataTransfer.getData('text/plain');
                        const rect = chart.getBoundingClientRect();
                        const x = e.clientX - rect.left - 90;
                        const y = e.clientY - rect.top - 60;
                        
                        const node = document.querySelector(`[data-user-id="${userId}"]`);
                        if (node) {
                            node.style.left = x + 'px';
                            node.style.top = y + 'px';
                            
                            // Update connections (simplified - in real app would need to recalculate)
                            this.showToast('Position updated', 'info');
                        }
                    });
                }
            }, 1000);
        });
    }
    
    initSearch() {
        const searchInput = document.createElement('input');
        searchInput.type = 'search';
        searchInput.id = 'hierarchySearch';
        searchInput.placeholder = 'Search users...';
        searchInput.className = 'form-control';
        searchInput.style.marginBottom = '20px';
        
        const hierarchyContainer = document.querySelector('.hierarchy-container');
        if (hierarchyContainer) {
            hierarchyContainer.prepend(searchInput);
            
            searchInput.addEventListener('input', (e) => {
                this.searchUsers(e.target.value);
            });
        }
    }
    
    filterProjects(searchTerm) {
        const projectCards = document.querySelectorAll('.project-card');
        const searchLower = searchTerm.toLowerCase();
        
        projectCards.forEach(card => {
            const title = card.querySelector('h4').textContent.toLowerCase();
            const description = card.querySelector('p').textContent.toLowerCase();
            
            if (title.includes(searchLower) || description.includes(searchLower)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    }
    
    searchUsers(searchTerm) {
        const nodes = document.querySelectorAll('.org-node');
        const searchLower = searchTerm.toLowerCase();
        
        nodes.forEach(node => {
            const name = node.querySelector('.org-node-name').textContent.toLowerCase();
            const role = node.querySelector('.org-node-title').textContent.toLowerCase();
            
            if (name.includes(searchLower) || role.includes(searchLower)) {
                node.style.opacity = '1';
                node.style.border = '2px solid var(--primary)';
            } else {
                node.style.opacity = '0.3';
                node.style.border = 'none';
            }
        });
    }
    
    exportOrganization() {
        const container = document.getElementById('orgChart');
        if (!container) return;
        
        // Create a canvas element
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
        // Set canvas size
        canvas.width = container.scrollWidth;
        canvas.height = container.scrollHeight;
        
        // Draw background
        ctx.fillStyle = 'white';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        
        // Draw all nodes
        const nodes = container.querySelectorAll('.org-node');
        nodes.forEach(node => {
            const rect = node.getBoundingClientRect();
            const containerRect = container.getBoundingClientRect();
            
            // Draw node background
            ctx.fillStyle = this.getNodeColor(node);
            ctx.fillRect(
                rect.left - containerRect.left,
                rect.top - containerRect.top,
                rect.width,
                rect.height
            );
            
            // Draw node border
            ctx.strokeStyle = '#dee2e6';
            ctx.lineWidth = 1;
            ctx.strokeRect(
                rect.left - containerRect.left,
                rect.top - containerRect.top,
                rect.width,
                rect.height
            );
            
            // Draw text
            ctx.fillStyle = 'white';
            ctx.font = 'bold 12px Arial';
            const title = node.querySelector('.org-node-title').textContent;
            const name = node.querySelector('.org-node-name').textContent;
            
            ctx.fillText(
                title,
                rect.left - containerRect.left + 10,
                rect.top - containerRect.top + 20
            );
            
            ctx.font = '10px Arial';
            ctx.fillText(
                name,
                rect.left - containerRect.left + 10,
                rect.top - containerRect.top + 40
            );
        });
        
        // Convert to image and download
        const image = canvas.toDataURL('image/png');
        const link = document.createElement('a');
        link.href = image;
        link.download = `organization-chart-${new Date().toISOString().split('T')[0]}.png`;
        link.click();
        
        this.showToast('Organization chart exported', 'success');
    }
    
    getNodeColor(node) {
        if (node.classList.contains('admin')) return '#4361ee';
        if (node.classList.contains('manager')) return '#f72585';
        if (node.classList.contains('team_leader')) return '#4cc9f0';
        return '#6c757d';
    }
    
    showModal(title, content, isUserDetails = false) {
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.style.display = 'flex';
        
        if (isUserDetails) {
            modal.innerHTML = `
                <div class="modal-content user-details-modal">
                    <button class="modal-close-float">&times;</button>
                    <div class="modal-body">
                        ${content}
                    </div>
                </div>
            `;
        } else {
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
        }
        
        document.body.appendChild(modal);
        
        // Add close handlers
        modal.querySelectorAll('.modal-close, .modal-close-float').forEach(btn => {
            btn.addEventListener('click', () => modal.remove());
        });
        
        // Close when clicking outside
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        });
    }
    
    closeAllModals() {
        document.querySelectorAll('.modal').forEach(modal => {
            modal.style.display = 'none';
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
        
        // Add CSS for animations if not already present
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
    
    showError(message) {
        this.showToast(message, 'error');
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.hierarchyManager = new HierarchyManager();
    window.hierarchyManager.init();
});