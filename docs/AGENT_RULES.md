# Domain-Based Tenant Architecture Rules

## 1. Core Architecture Principle

This project uses a Domain-Based Multi-Tenant Laravel architecture.

Every business concept lives inside its own domain folder.  
Domains are self-contained and own their models, schema, and UI structure.

---

## 2. Domain Structure Standard

All record types must live under:


app/Domain/{DomainName}/


### Example: Location Domain


app/Domain/Location/
├── Models/
│ └── Location.php
├── Schema/
│ ├── fields.json
│ ├── form.json
│ └── table.json


---

## 3. Domain Responsibilities

Each domain is responsible for:

### Models

Example:

app/Domain/Location/Models/Location.php


Rules:
- Only domain-specific logic belongs here
- No cross-domain logic unless through services or events
- Models should not contain UI logic

---

### Schema Layer (Meta Configuration)

Located in:

app/Domain/{Domain}/Schema/


Files:
- `fields.json` → defines data structure
- `form.json` → defines create/edit form layout
- `table.json` → defines index/table columns

Rules:
- Schema is the single source of truth for structure and UI rendering
- Do NOT duplicate schema definitions in Vue or controllers
- UI must be driven from schema where possible

---

## 4. Record Types Registry

All record types are centrally tracked in:


app/Enums/RecordType.php


Rules:
- Every domain must register its record type here
- RecordType is the system-wide identifier for routing and rendering
- Used for polymorphism, UI resolution, and data mapping

---

## 5. Public Preview Documents

Public-facing preview components must follow a strict standard.

### Location


resources/js/Components/Tenant/{RecordType}Preview.vue


---

### Required UI Structure

All preview pages MUST use shared layout components:

- Shared Header component
- Shared Footer component

Rules:
- DO NOT duplicate header or footer logic per preview
- All previews must use shared components for consistency

---

### Example Preview Components

- `resources/js/Pages/Tenant/Public/ServiceTicketReview.vue`
- `resources/js/Pages/Tenant/Public/DeliveryReview.vue`
- `resources/js/Pages/Tenant/Public/ContractReview.vue`

---

## 6. Internal Record Show Pages

All internal “show” pages must follow the standard layout pattern.

### Reference Implementation


resources/js/Pages/Tenant/Estimate/Show.vue


---

### Layout Requirements

- Blue header bar
- Action buttons in right sidebar
- Clean two-column layout structure

Rules:
- This layout is the standard for all record show pages
- Do NOT create custom layouts per domain
- Maintain UI consistency across all record types ulness stated otherwise

---

## 7. UI Consistency Rules

### Hard Rules

- All domains must share the same UI system
- Layout, actions, and structure must be consistent
- No domain-specific UI frameworks allowed

---

## 8. Component Reuse Rules

### MUST reuse:
- Header components
- Footer components
- Record show layouts
- Table components where applicable

### MUST NOT:
- Duplicate preview layouts per domain
- Build custom page structures per domain
- Embed schema logic directly inside Vue components

---

## 9. System Mental Model

### Domains = Backend Ownership

Each domain owns:
- Data structure
- Business logic
- Schema definitions

Example:

Location = owns everything about locations


---

### Vue Components = Presentation Layer Only

Vue components:
- Render data only
- Do not define business rules
- Are driven by schema and record type

---

### RecordType = System Glue

RecordType connects:
- Routes
- Domains
- Schema
- UI rendering

---

## 10. Recommended Enhancement (Optional)

Consider adding a domain manifest:


app/Domain/Location/domain.json


### Example:

```json
{
  "name": "Location",
  "recordType": "location",
  "model": "App\\Domain\\Location\\Models\\Location",
  "schemaPath": "Schema"
}