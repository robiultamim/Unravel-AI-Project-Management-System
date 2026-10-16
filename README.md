# Unravel — AI-Powered Enterprise Agile Project Management Platform

[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-777BB4.svg?logo=php&logoColor=white)](#technology-stack)
[![Database](https://img.shields.io/badge/Database-MySQL%20%7C%20MariaDB-4479A1.svg?logo=mysql&logoColor=white)](#database-architecture)
[![AI Engine](https://img.shields.io/badge/AI-Groq%20LLaMA--3.1--8B-F05032.svg?logo=meta&logoColor=white)](#ai-powered-task-breakdown)
[![Architecture](https://img.shields.io/badge/Architecture-Modular%20MVC%20%2F%20AJAX-28a745.svg)](#system-architecture)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![Author](https://img.shields.io/badge/Author-robiultamim-blue.svg)](https://github.com/robiultamim)

**Unravel** is an intelligent, full-stack enterprise agile project management and organizational coordination platform. Built with **PHP**, **MySQL**, and **Groq LLaMA-3.1 AI**, Unravel unifies interactive **Kanban boards**, **Gantt timelines**, **multi-level organizational hierarchy visualization**, an **SOS employee distress escalation mechanism**, **skill-based AI task allocation**, and **gamified performance analytics** into a cohesive system.

---

## Table of Contents

- [Overview](#overview)
- [Key Features](#key-features)
- [System Architecture](#system-architecture)
- [AI Engine & Intelligent Algorithms](#ai-engine--intelligent-algorithms)
  - [1. AI Task Breakdown (Groq LLaMA-3.1)](#1-ai-task-breakdown-groq-llama-31)
  - [2. Intelligent Skill-Based Task Assignment Engine](#2-intelligent-skill-based-task-assignment-engine)
- [Role-Based Access Control (RBAC)](#role-based-access-control-rbac)
- [Core Application Modules](#core-application-modules)
  - [Interactive Kanban Board](#interactive-kanban-board)
  - [Gantt Chart & Dependency Visualization](#gantt-chart--dependency-visualization)
  - [Organizational Hierarchy Visualizer](#organizational-hierarchy-visualizer)
  - [SOS Distress & Escalation System](#sos-distress--escalation-system)
  - [Performance Leaderboards & Ratings](#performance-leaderboards--ratings)
  - [Daily Mood & Pulse Tracker](#daily-mood--pulse-tracker)
- [Database Architecture & Schema](#database-architecture--schema)
- [AJAX API Endpoints](#ajax-api-endpoints)
- [Technology Stack](#technology-stack)
- [Project Directory Structure](#project-directory-structure)
- [Installation & Setup Guide](#installation--setup-guide)
  - [Prerequisites](#prerequisites)
  - [Step 1: Clone Repository](#step-1-clone-repository)
  - [Step 2: Database Setup & Import](#step-2-database-setup--import)
  - [Step 3: Configuration & Environment Variables](#step-3-configuration--environment-variables)
  - [Step 4: Launch Application](#step-4-launch-application)
- [Pre-configured Demo Accounts](#pre-configured-demo-accounts)
- [Security & Architecture Standards](#security--architecture-standards)
- [Contributing & License](#contributing--license)

---

## Overview

Modern software development and enterprise teams frequently suffer from workflow fragmentation:
- **High Cognitive Overhead:** Developers spend hours decomposing massive Epics/Features into actionable subtasks.
- **Suboptimal Task Assignments:** Managers struggle to match task skill requirements with engineer proficiencies while balancing workloads.
- **Silent Blocker Accumulation:** Engineers who get stuck often hesitate to ask for help, delaying sprint deliverables until retro.
- **Opaque Reporting Hierarchies:** Cross-departmental projects lose transparency between executives, engineering managers, team leads, and individual contributors.

### The Unravel Solution
**Unravel** resolves these operational bottlenecks through a streamlined, AI-enhanced workflow:
1. **Instant AI Decomposition:** Automatically decomposes complex tasks into concise, numbered micro-steps using Groq-hosted LLaMA-3.1.
2. **Algorithmic Skill Allocation:** Evaluates technical competencies, proficiency tiers, and active loads to recommend ideal assignees.
3. **Rapid Blocker Escalation (SOS):** Provides an immediate "I'm Stuck" distress pipeline connecting blocked developers with team leaders for real-time resolution.
4. **End-to-End Agile Observability:** Combines drag-and-drop Kanban, milestone Gantt charts, organizational tree graphs, and automated email alerts.

---

## Key Features

- 🤖 **Groq LLaMA-3.1 AI Task Breakdown:** Decomposes complex deliverables into concise, structured, numbered subtasks in under 120 words with 1-click execution.
- 🎯 **Algorithmic Skill-to-Task Matching:** Calculates matching skill counts, proficiency weights (`beginner` to `expert`), and active capacity to recommend candidate assignees.
- 📋 **Interactive Kanban Board:** 5-column responsive drag-and-drop workflow (`To Do`, `In Progress`, `Done`, `Approved`, `Blocked`) with instant AJAX persistence.
- 📊 **Dynamic Gantt Chart:** Visualizes project timelines, parent milestones, dates, assignees, and cross-task prerequisites (`task_dependencies`).
- 🏢 **Dynamic Organizational Hierarchy Visualizer:** Interactive organizational structure tree (`Admin` ➔ `Manager` ➔ `Team Leader` ➔ `Employee`) with on-the-fly onboarding and credential dispatch.
- 🚨 **SOS Distress & Escalation Management:**
  - *Employee Portal:* Instant distress beacon for blocked tasks with root-cause categorization.
  - *Team Leader Command:* Live incident queue with intervention dispatch, response logging, and unblocking metrics.
- 🏆 **Gamified Leaderboards & Ratings:** Monthly evaluations across timeliness, quality, and communication, coupled with performance bonus tracking.
- 🔔 **Multi-Channel Notification System:** Real-time in-app badge notifications combined with PHPMailer SMTP email dispatch for task assignments, deadlines, and invitations.
- 🧠 **Daily Employee Mood & Pulse Tracker:** Continuous team sentiment monitoring to detect burnout early.
- 🛡️ **Granular Role-Based Access Control (RBAC):** Strict 4-tier hierarchical authorization protecting administrative and project routes.

---

## System Architecture

Unravel follows a modular, decoupled MVC-inspired architecture with asynchronous AJAX communication and secure PDO database abstractions:

```
┌────────────────────────────────────────────────────────────────────────┐
│                        Client Layer (Browser)                          │
│   • Responsive HTML5 / CSS3 Grid & Flexbox                             │
│   • Asynchronous Fetch / AJAX Controllers (kanban.js, hierarchy.js)    │
│   • FontAwesome 6.4 Vector Icons                                       │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │ HTTP / JSON Requests
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│                        Application Gate & Routing                      │
│   • Session Guard & RBAC Authorization (Session::checkRoleAccess)       │
│   • Input Validation & CSRF Protection Layer                           │
└───────────────────┬────────────────────────────────┬───────────────────┘
                    │                                │
                    ▼                                ▼
┌──────────────────────────────────────┐  ┌──────────────────────────────┐
│       Core PHP View Controllers      │  │      RESTful AJAX APIs       │
│   • dashboard.php   • kanban.php     │  │   • generate_breakdown.php   │
│   • gantt.php       • hierarchy.php  │  │   • update_task_status.php   │
│   • sos-*.php       • reports.php    │  │   • assign_task.php          │
│   • tasks.php       • profile.php    │  │   • send_sos.php             │
└───────────────────┬──────────────────┘  └──────────────┬───────────────┘
                    │                                    │
                    └──────────────────┬─────────────────┘
                                       ▼
┌────────────────────────────────────────────────────────────────────────┐
│                       Service & Business Engines                       │
│   ┌──────────────────────────┐      ┌──────────────────────────────┐   │
│   │   Groq LLaMA-3.1 API     │      │   Skill Matching Algorithm   │   │
│   │   (Task Decomposition)   │      │   (Workload & Proficiency)   │   │
│   └──────────────────────────┘      └──────────────────────────────┘   │
│   ┌──────────────────────────┐      ┌──────────────────────────────┐   │
│   │   PHPMailer (SMTP TLS)   │      │   Notification Dispatcher    │   │
│   │   (Email Notifications)  │      │   (In-App Bell & Signals)    │   │
│   └──────────────────────────┘      └──────────────────────────────┘   │
└──────────────────────────────────────┬─────────────────────────────────┘
                                       │ PDO MySQL Prepared Statements
                                       ▼
┌────────────────────────────────────────────────────────────────────────┐
│                        MySQL / MariaDB Database                        │
│   • Users & RBAC Hierarchy       • Projects & Milestones               │
│   • Tasks & Dependencies         • Skills & Competency Matrix          │
│   • SOS Signals & Audit Logs     • Leaderboard & Performance Ratings   │
└────────────────────────────────────────────────────────────────────────┘
```

---

## AI Engine & Intelligent Algorithms

### 1. AI Task Breakdown (Groq LLaMA-3.1)
When managing complex parent deliverables, users can trigger the AI Breakdown engine. The system queries Groq's high-throughput cloud inference endpoint running **`llama-3.1-8b-instant`**:
- **Prompt Architecture:** Supplies the task title, description, and parent milestone context to an expert agile persona prompt.
- **Constraints:** Enforces strict 120-word limits with clean numbered execution steps.
- **Latency:** Sub-second inference time via Groq LPU engine.

```
Task: "Implement OAuth2 Social Authentication"
  │
  ▼ Groq LLaMA-3.1-8B Inference (< 400ms)
  │
  ├─► 1. Register OAuth Application credentials in Google & GitHub consoles.
  ├─► 2. Create database migration for provider_id and access_token schema.
  ├─► 3. Implement OAuth redirect handler & state CSRF validation controller.
  ├─► 4. Implement callback consumer with profile extraction & user linking.
  └─► 5. Write unit and integration tests for session generation and token expiry.
```

### 2. Intelligent Skill-Based Task Assignment Engine
Instead of arbitrary assignments, Unravel evaluates candidate fitness using a multi-factor ranking formula:

$$\text{Rank Score} = \Big(w_1 \cdot M_{\text{skills}}\Big) + \Big(w_2 \cdot P_{\text{avg}}\Big) - \Big(w_3 \cdot L_{\text{current}}\Big) + \Big(w_4 \cdot T_{\text{hist}}\Big)$$

Where:
- $M_{\text{skills}}$: Count of matching skills required by the task (`task_skills` $\cap$ `user_skills`).
- $P_{\text{avg}}$: Average candidate proficiency weight (`beginner` = 1, `intermediate` = 2, `advanced` = 3, `expert` = 4).
- $L_{\text{current}}$: Current active workload count (tasks currently in `todo` or `in_progress`).
- $T_{\text{hist}}$: Historical task timeliness rating derived from completion audit history.

If no specific skill tags exist, the engine automatically defaults to **workload balancing**, choosing the eligible engineer with the lowest active queue and highest timeliness rating.

---

## Role-Based Access Control (RBAC)

Unravel implements a 4-tier hierarchical authorization model:

| Capability / Module | Admin | Manager | Team Leader | Employee |
| :--- | :---: | :---: | :---: | :---: |
| **System Settings & Full Audit** | ✅ | ❌ | ❌ | ❌ |
| **Organizational Hierarchy Visualizer** | ✅ | ❌ | ❌ | ❌ |
| **Create & Initialize Projects** | ✅ | ✅ | ❌ | ❌ |
| **Assign Managers to Projects** | ✅ | ❌ | ❌ | ❌ |
| **Assign Team Leaders & Staff** | ✅ | ✅ | ❌ | ❌ |
| **Parent Task & Milestone Management** | ✅ | ✅ | ✅ | ❌ |
| **Task Creation & AI Breakdown** | ✅ | ✅ | ✅ | ✅ |
| **Drag-and-Drop Kanban Board** | ✅ | ✅ | ✅ | ✅ |
| **Gantt Chart Project Timeline** | ✅ | ✅ | ✅ | ✅ (Assigned) |
| **SOS Escalation Resolution Command** | ❌ | ❌ | ✅ | ❌ |
| **Trigger "I'm Stuck" SOS Signal** | ❌ | ❌ | ❌ | ✅ |
| **Monthly Ratings & Bonus Allocation** | ✅ | ✅ | ✅ | ❌ |
| **Daily Mood & Pulse Check-in** | ❌ | ❌ | ❌ | ✅ |
| **Departmental Analytics & Reports** | ✅ | ✅ | ✅ | ❌ |

---

## Core Application Modules

### Interactive Kanban Board
- **5 Dynamic Status Columns:** `To Do`, `In Progress`, `Done`, `Approved`, `Blocked`.
- **Drag-and-Drop:** Intuitive card movement with instant AJAX status synchronization.
- **Priority Indicators:** Color-coded badges for `Low`, `Medium`, `High`, and `Critical`.
- **Task Modals:** Detailed task metadata inspection including assignees, estimates, parent tasks, and deadline indicators.

### Gantt Chart & Dependency Visualization
- **Project Schedules:** Timeline rendering tracking start and due dates for all project tasks.
- **Dependency Mapping:** Visualizes task prerequisites to ensure sequential execution.
- **Progress Tracking:** Live calculation of completion percentages across project milestones.

### Organizational Hierarchy Visualizer
- **Interactive Tree Graph:** Renders multi-tiered organizational trees from root Administrators to departmental Employees.
- **Rapid Provisioning:** Form to invite new Managers or Team Leaders with auto-generated credentials and automated SMTP email dispatch.

### SOS Distress & Escalation System
- **Employee Portal:** Dedicated interface (`sos-employee.php`) allowing team members to raise immediate distress signals for blocked tasks.
- **Team Leader Console:** Live dashboard (`sos-team-leader.php`) aggregating pending SOS tickets, displaying blockers, and tracking response history.

### Performance Leaderboards & Ratings
- **Gamified Standings:** Daily, weekly, and monthly rankings computed from completed deliverables and logged hours.
- **Manager Evaluations:** Quantitative monthly scorecards assessing timeliness, code/deliverable quality, and team communication with bonus distribution tracking.

### Daily Mood & Pulse Tracker
- Enables team members to submit daily sentiment ratings (`1` to `5`) and feedback, giving leadership an early indicator of team morale.

---

## Database Architecture & Schema

The underlying MariaDB/MySQL database (`project_management`) consists of 18 normalized tables and 2 optimized views:

```
                           ┌──────────────────┐
                           │      users       │
                           └────────┬─────────┘
                                    │
         ┌──────────────────────────┼──────────────────────────┐
         │ 1:N                      │ 1:N                      │ 1:N
         ▼                          ▼                          ▼
┌──────────────────┐       ┌──────────────────┐       ┌──────────────────┐
│  user_hierarchy  │       │   user_skills    │       │  notifications   │
└──────────────────┘       └────────┬─────────┘       └──────────────────┘
                                    │ N:1
                                    ▼
┌──────────────────┐       ┌──────────────────┐       ┌──────────────────┐
│     projects     │◄──────┤      skills      │       │   leaderboard    │
└────────┬─────────┘       └────────┬─────────┘       └──────────────────┘
         │                          │
         │ 1:N                      │ N:1
         ▼                          ▼
┌──────────────────┐       ┌──────────────────┐       ┌──────────────────┐
│   parent_task    │       │   task_skills    │       │   sos_signals    │
└────────┬─────────┘       └────────┬─────────┘       └────────┬─────────┘
         │                          │                          │
         │ 1:N                      │ N:1                      │ N:1
         ▼                          ▼                          │
┌─────────────────────────────────────────────┐                │
│                    tasks                    │◄───────────────┘
└──────────────────────┬──────────────────────┘
                       │
         ┌─────────────┴─────────────┐
         │ 1:N                       │ 1:N
         ▼                           ▼
┌──────────────────┐        ┌──────────────────┐
│task_dependencies │        │    work_logs     │
└──────────────────┘        └──────────────────┘
```

### Table Dictionary

| Table Name | Description | Key Fields |
| :--- | :--- | :--- |
| `users` | Core user identity, role, and authentication credentials | `user_id`, `username`, `email`, `password_hash`, `role` |
| `user_hierarchy` | Direct reporting line relationships | `id`, `user_id`, `reports_to` |
| `projects` | High-level project specifications, managers, and schedules | `project_id`, `project_name`, `admin_id`, `manager_id`, `status` |
| `project_members` | Project team assignments and member roles | `project_id`, `user_id`, `role_in_project` |
| `parent_task` | High-level milestones or epics within projects | `parent_task_id`, `parent_task_name`, `project_id` |
| `tasks` | Actionable work units with deadlines, priority, and state | `task_id`, `project_id`, `parent_task_id`, `assigned_to`, `status` |
| `task_dependencies`| Directed acyclic graph (DAG) of task prerequisites | `id`, `task_id`, `depends_on_task_id` |
| `skills` | Master catalog of technical and soft skills | `skill_id`, `skill_name`, `category` |
| `task_skills` | Skills required to complete a given task | `task_id`, `skill_id`, `required_level` |
| `user_skills` | Technical skill proficiencies declared by employees | `user_id`, `skill_id`, `proficiency_level` |
| `sos_signals` | Developer distress beacons for blocked deliverables | `sos_id`, `task_id`, `raised_by`, `status`, `reason` |
| `leaderboard` | Periodic productivity rankings and hours logged | `id`, `user_id`, `period_type`, `score`, `rank_position` |
| `performance_ratings`| Monthly managerial scorecards and bonus allocations | `rating_id`, `user_id`, `rated_by`, `overall_score`, `bonus_amount` |
| `notifications` | In-app alerts for assignments, deadlines, and alerts | `notification_id`, `user_id`, `title`, `type`, `is_read` |
| `reminders` | Automated notification triggers for approaching deadlines | `reminder_id`, `task_id`, `user_id`, `reminder_type` |
| `work_logs` | Time tracking records and activity notes | `log_id`, `task_id`, `user_id`, `hours_worked` |
| `employee_summary`| Database view consolidating productivity metrics | `user_id`, `tasks_completed`, `avg_rating`, `total_bonus` |
| `sos_alerts_view` | Database view consolidating unresolved SOS signals | `sos_id`, `task_title`, `employee_name`, `team_leader_id` |

---

## AJAX API Endpoints

All asynchronous operations communicate via JSON-based PHP endpoints in the `/ajax` directory:

| Endpoint | Method | Purpose | Key Parameters |
| :--- | :---: | :--- | :--- |
| `ajax/generate_breakdown.php` | `GET` | Invokes Groq LLaMA-3.1 to decompose a task into subtasks | `task_id` |
| `ajax/update_task_status.php` | `POST`| Updates task status on Kanban drag-and-drop | `task_id`, `status` |
| `ajax/assign_task.php` | `POST`| Reassigns a task to a user | `task_id`, `user_id` |
| `ajax/save_task.php` | `POST`| Creates or updates task metadata and deadlines | Form payload |
| `ajax/get_task.php` | `GET` | Fetches JSON data for a specific task | `task_id` |
| `ajax/get_tasks.php` | `GET` | Retrieves tasks filtered by project, status, or assignee | `project_id`, `status` |
| `ajax/get_task_details.php` | `GET` | Detailed metadata including dependencies and skills | `task_id` |
| `ajax/send_sos.php` | `POST`| Raises an SOS distress ticket for a blocked deliverable | `task_id`, `reason` |
| `ajax/submit_mood.php` | `POST`| Logs daily employee mood and pulse check-in | `mood_score`, `comments` |
| `ajax/get_organization_data.php`| `GET` | Fetches hierarchical tree nodes for the visualizer | `project_id` |
| `ajax/get_project_members.php` | `GET` | Lists users associated with a specific project | `project_id` |

---

## Technology Stack

- **Backend:** PHP 8.0+ (Object-Oriented, PDO Prepared Statements, Session State Management, cURL)
- **Database:** MySQL 8.0+ / MariaDB 10.4+ (Indexed relational schema, Views, Foreign Keys)
- **AI / LLM Cloud:** Groq Cloud Inference API (`llama-3.1-8b-instant`)
- **Mailing Engine:** PHPMailer 6.8+ (Authenticated SMTP over TLS)
- **Frontend:** Vanilla JavaScript (ES6+ Fetch, DOM manipulation), CSS3 Custom Properties, Responsive Flex/Grid
- **Design & Icons:** FontAwesome 6.4.0, Google Fonts (Segoe UI / Roboto)

---

## Project Directory Structure

```
unravel/
├── ajax/                             # Asynchronous API endpoints
│   ├── assign_task.php               # Task assignment handler
│   ├── generate_breakdown.php        # Groq AI task decomposition
│   ├── get_organization_data.php     # Hierarchy tree data feed
│   ├── get_project_members.php       # Member retrieval endpoint
│   ├── get_projects.php              # Project list endpoint
│   ├── get_task.php                  # Single task fetcher
│   ├── get_task_breakdown.php        # Subtask retrieval
│   ├── get_task_count.php            # Metric counters
│   ├── get_task_details.php          # Comprehensive task metadata
│   ├── get_tasks.php                 # Multi-task querying
│   ├── get_user_details.php          # User profile endpoint
│   ├── save_task.php                 # Task creation/editing
│   ├── send_sos.php                  # SOS distress dispatch
│   ├── submit_mood.php               # Mood pulse recorder
│   └── update_task_status.php        # Kanban drag-and-drop updater
├── assets/                           # Static assets
│   ├── css/                          # Modular stylesheet library
│   │   ├── auth.css                  # Authentication screens
│   │   ├── dashboard.css             # Main dashboard UI
│   │   ├── gantt.css                 # Gantt chart timeline styling
│   │   ├── hierarchy.css             # Tree node visualizer styling
│   │   ├── kanban.css                # Drag-and-drop Kanban styling
│   │   ├── manager.css               # Project manager controls
│   │   ├── notifications.css         # Notification tray styling
│   │   ├── profile.css               # User profile & skill matrix
│   │   ├── reports.css               # Analytics & report tables
│   │   ├── style.css                 # Global design system & layout
│   │   ├── tasks.css                 # Task view layouts
│   │   └── team.css                  # Team view styling
│   └── js/                           # Client-side JavaScript controllers
│       ├── auth.js                   # Client-side form validation
│       ├── hierarchy.js              # Interactive tree rendering engine
│       └── kanban.js                 # Drag-and-drop Kanban controller
├── config/                           # Application configuration
│   ├── database.php                  # PDO database connection class
│   ├── email.php                     # PHPMailer SMTP client service
│   └── session.php                   # Authentication & RBAC session guard
├── includes/                         # Reusable layout components
│   ├── footer.php                    # Global page footer
│   ├── functions.php                 # Core utilities & AI skill recommender
│   ├── header.php                    # Top navigation bar & user badge
│   └── sidebar.php                   # Dynamic role-based navigation menu
├── uploads/                          # User-uploaded files
│   └── profiles/                     # User avatar storage (.gitkeep)
├── vendor/                           # Composer dependencies
│   ├── composer/                     # Autoloading definitions
│   ├── phpmailer/                    # PHPMailer library
│   └── autoload.php                  # Master autoloader
├── .env.example                      # Environment variables template
├── .gitignore                        # Git exclusion rules
├── dashboard.php                     # Main role-tailored dashboard
├── gantt.php                         # Visual Gantt chart timeline page
├── hierarchy.php                     # Admin organizational visualizer
├── index.php                         # Entry point redirector
├── kanban.php                        # Interactive Kanban board page
├── LICENSE                           # MIT License
├── login.php                         # Secure authentication portal
├── logout.php                        # Session termination script
├── manager-projects.php              # Project management interface
├── notifications.php                 # In-app notifications center
├── parent-task-manager.php           # Parent task / Epic manager
├── profile.php                       # User profile, skills, & password
├── project_management (2).sql        # Full database schema and seed data
├── README.md                         # Project documentation
├── register.php                      # User registration portal
├── reports.php                       # Performance & project reporting
├── sos-employee.php                  # Employee "I'm Stuck" SOS portal
├── sos-team-leader.php               # Team Leader SOS resolution command
├── tasks.php                         # Centralized task management
└── team-view.php                     # Team leader team overview
```

---

## Installation & Setup Guide

### Prerequisites
Before running the application, ensure your environment has:
- **PHP 8.0 or higher** with `pdo_mysql`, `curl`, and `openssl` extensions enabled.
- **MySQL 8.0+** or **MariaDB 10.4+**.
- **A local server environment** such as [XAMPP](https://www.apachefriends.org/), [WAMP](https://www.wampserver.com/), [Laragon](https://laragon.org/), or PHP's built-in CLI server.

---

### Step 1: Clone Repository
```bash
git clone https://github.com/robiultamim/Unravel-AI-Project-Management-System.git
cd Unravel-AI-Project-Management-System
```

---

### Step 2: Database Setup & Import
1. Start your **MySQL / MariaDB** server (e.g., via the XAMPP Control Panel).
2. Open **phpMyAdmin** (`http://localhost/phpmyadmin`) or your preferred MySQL client.
3. Create a new database named **`project_management`**:
   ```sql
   CREATE DATABASE project_management CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
   ```
4. Import the provided schema and seed data file:
   - Via phpMyAdmin: Click on `project_management` ➔ **Import** tab ➔ Choose `project_management (2).sql` ➔ Click **Go**.
   - Via Command Line:
     ```bash
     mysql -u root -p project_management < "project_management (2).sql"
     ```

---

### Step 3: Configuration & Environment Variables

#### Database Credentials
Verify or adjust your database credentials in `config/database.php`:
```php
private $host = "localhost";
private $db_name = "project_management";
private $username = "root";
private $password = "";
```

#### Groq AI & SMTP Setup
Copy the environment example file:
```bash
cp .env.example .env
```
Configure your environment variables in your server or `.env`:
- **Groq API Key:** Obtain a free API key from [Groq Console](https://console.groq.com/keys) to enable AI Task Breakdown.
- **SMTP Settings:** Configure Gmail App Passwords or custom SMTP server details to enable automated invitation and assignment emails.

---

### Step 4: Launch Application

#### Option A: PHP Built-in Server (Fastest)
Open your terminal inside the project directory and run:
```bash
php -S localhost:8000
```
Then navigate to: **[http://localhost:8000](http://localhost:8000)**

#### Option B: XAMPP / WAMP / Laragon
Move or symlink the folder into your web server's document root (e.g., `C:/xampp/htdocs/unravel`) and visit:
```
http://localhost/unravel
```

---

## Pre-configured Demo Accounts

The imported database includes seeded test accounts across all roles:

| Username | Default Role | Department | Access Level |
| :--- | :--- | :--- | :--- |
| **`admin1`** | **Admin** | Management | Full administrative access, hierarchy tree, user management |
| **`manager1`** | **Manager** | Development | Project creation, milestone oversight, reports |
| **`teamlead1`**| **Team Leader**| Development | Sprint assignments, team view, SOS resolution command |
| **`emp1`** | **Employee** | Development | Tasks, Kanban board, Gantt chart, SOS distress beacon |
| **`emp2`** | **Employee** | Design | Tasks, Kanban board, profile & skill portfolio |

> **Note:** To register a custom account with your own credentials, visit `register.php`. By default, newly registered users are provisioned as **Manager** accounts and can be promoted to **Admin** via the database.

---

## Security & Architecture Standards

1. **SQL Injection Defense:** All database interactions utilize **PDO Prepared Statements** with explicit parameter binding.
2. **Session Hijacking Mitigation:** Session states are strictly verified on every page controller via `Session::redirectIfNotLoggedIn()`.
3. **Cross-Site Scripting (XSS) Prevention:** Output rendering uses `htmlspecialchars()` escaping across all user-generated strings.
4. **Credential Security:** User passwords are encrypted with industry-standard **bcrypt** hashing (`PASSWORD_BCRYPT`).
5. **Decoupled Architecture:** Business logic and AI services are compartmentalized in `/includes` and `/ajax` for maintainability and scalability.

---

## Contributing & License

Contributions, issues, and feature requests are welcome!

1. Fork the project repository.
2. Create your feature branch (`git checkout -b feature/AmazingFeature`).
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`).
4. Push to the branch (`git push origin feature/AmazingFeature`).
5. Open a Pull Request.

Distributed under the **MIT License**. See [`LICENSE`](LICENSE) for more information.

---

<p align="center">
  <b>Developed & Maintained by <a href="https://github.com/robiultamim">Robiul Islam Tamim</a></b><br>
  <sub>For questions, collaborations, or inquiries, reach out at <a href="mailto:robiulislamtamim12345@gmail.com">robiulislamtamim12345@gmail.com</a>.</sub>
</p>
