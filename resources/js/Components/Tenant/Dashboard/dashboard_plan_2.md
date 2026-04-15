# Helmful Dashboard System Architecture

## Overview

This document defines the architecture and implementation plan for a modular, customizable dashboard system in Helmful. The dashboard is designed as a **widget-based command center**, not a static reporting page.

The system enables:
- Per-user dashboard customization
- Drag-and-drop layout control
- Modular widget rendering
- Scalable future expansion (roles, permissions, marketplace)

---

# Core Principles

1. Dashboard = **Action-first**, not data-first
2. Everything should be **clickable and actionable**
3. Layout is **data-driven (JSON)**, not hardcoded
4. Widgets are **independent, reusable components**
5. System must support **future customization and scaling**

---

# Architecture Overview

The dashboard consists of three main layers:

## 1. Widget Registry (Available Widgets)
Defines all widgets available in the system.

## 2. Layout Configuration (User State)
Stores which widgets a user has and how they are arranged.

## 3. Widget Renderer (Vue Layer)
Dynamically renders widgets based on layout config.

---

# 1. Widget Registry

Central source of truth for all widgets.

```js
// resources/js/dashboard/widgets.js

import ActionCenter from '@/Components/Dashboard/Widgets/ActionCenter.vue'
import RevenueSnapshot from '@/Components/Dashboard/Widgets/RevenueSnapshot.vue'
import RiskPanel from '@/Components/Dashboard/Widgets/RiskPanel.vue'
import Operations from '@/Components/Dashboard/Widgets/Operations.vue'
import ActivityFeed from '@/Components/Dashboard/Widgets/ActivityFeed.vue'

export const widgetRegistry = {
    action_center: {
        name: 'Action Center',
        component: ActionCenter,
        defaultSize: { w: 2, h: 2 },
    },
    revenue: {
        name: 'Revenue Snapshot',
        component: RevenueSnapshot,
        defaultSize: { w: 1, h: 1 },
    },
    risk: {
        name: 'Attention Required',
        component: RiskPanel,
        defaultSize: { w: 1, h: 1 },
    },
    operations: {
        name: 'Operations',
        component: Operations,
        defaultSize: { w: 1, h: 1 },
    },
    activity: {
        name: 'Activity Feed',
        component: ActivityFeed,
        defaultSize: { w: 1, h: 2 },
    },
}
2. Layout Configuration (Database)

Stored per user as JSON.

Example Layout
[
    { "i": "action_center", "x": 0, "y": 0, "w": 2, "h": 2 },
    { "i": "revenue", "x": 2, "y": 0, "w": 1, "h": 1 },
    { "i": "risk", "x": 2, "y": 1, "w": 1, "h": 1 },
    { "i": "operations", "x": 0, "y": 2, "w": 2, "h": 1 },
    { "i": "activity", "x": 2, "y": 2, "w": 1, "h": 2 }
]
Storage Options
users.dashboard_layout (JSON column)
or dedicated dashboard_layouts table
3. Vue Dashboard Renderer

Uses a grid system (recommended: vue3-grid-layout)

Dashboard.vue
<script setup>
import { ref } from 'vue'
import { widgetRegistry } from './widgets'

const layout = ref($page.props.dashboardLayout)
</script>

<template>
  <GridLayout
    v-model:layout="layout"
    :col-num="3"
    :row-height="120"
    :is-draggable="true"
    :is-resizable="true"
  >
    <GridItem
      v-for="item in layout"
      :key="item.i"
      v-bind="item"
    >
      <component :is="widgetRegistry[item.i].component" />
    </GridItem>
  </GridLayout>
</template>
4. Saving Layout

Persist layout changes to backend.

const saveLayout = async () => {
    await axios.post('/dashboard/layout', {
        layout: layout.value
    })
}

Bind to grid event:

@layout-updated="saveLayout"
5. Adding / Removing Widgets
Available Widgets
const availableWidgets = Object.entries(widgetRegistry)
Add Widget
layout.value.push({
    i: 'activity',
    x: 0,
    y: Infinity,
    w: 1,
    h: 2
})
6. Global Filters (Critical)

Dashboard should support shared filters:

Date range
Location
Subsidiary
Example
const filters = ref({
    range: '30d',
    location_id: null,
})

Pass to widgets:

<component
  :is="widgetRegistry[item.i].component"
  :filters="filters"
/>
7. Widget Responsibilities

Each widget should:

Fetch its own data OR receive it via props
Be self-contained
Handle loading + empty states
Provide actionable UI
8. Core Dashboard Widgets
Action Center (Top Priority)
Tasks due today
Follow-ups
Deliveries
Service tasks
Revenue Snapshot
MTD revenue
Outstanding balance
Open deals value
Attention / Risk Panel
Overdue invoices
Stalled deals
Expiring estimates
Operations Snapshot
Open service tickets
Work orders
Deliveries
Activity Feed
Recent system activity
Payments
Leads
Updates
9. Permissions (Future)

Extend registry:

permissions: ['view_payments']

Filter widgets:

Object.entries(widgetRegistry).filter(([key, widget]) => {
    return hasPermission(widget.permissions)
})
10. UX Requirements

Each widget should include:

Header (title + actions)
Loading skeleton
Empty state
Clickable rows
Quick actions
11. System Design Principles
Do:
Make everything actionable
Keep widgets focused
Use consistent spacing/layout
Optimize for speed
Do Not:
Build a reporting dashboard
Duplicate navigation
Overload with data
12. Future Enhancements
Per-role default dashboards
Saved layouts (Sales vs Service)
Widget marketplace
Real-time updates (WebSockets)
Drag-and-drop widget library panel
Final Mental Model
Dashboard = Layout (JSON) + Widget Registry (Components)

This enables:

Full flexibility
Clean architecture
Long-term scalability
Priority Implementation Order
Build Widget Registry
Build Grid Layout Renderer
Store Layout JSON per user
Implement Action Center widget
Add Revenue + Risk widgets
Add Save Layout functionality
Add Widget picker modal
End Goal

A dashboard that feels like:

"Here’s exactly what I need to run my dealership today."

Not:

"Here’s a bunch of data."
