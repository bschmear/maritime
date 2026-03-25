Boat Show Layout Builder – Implementation Spec
Overview

We are building a Boat Show Layout Builder that allows users to visually place boats within a defined space (in feet) for a specific boat show event.

This feature includes:

Boat Shows (already exists)
Boat Show Events (already exists)
Layouts (canvas with dimensions)
Layout Items (boats placed on canvas)

The system must support:

Visual placement (x, y in feet)
Boat dimensions (length, width)
Rotation (0, 90, 180, 270)
Editable dimensions after placement
Snapshot data (do NOT rely on live boat data after placement)
Data Model
1. boat_show_layouts

Represents a layout (canvas) tied to a boat show event.

Fields:

id
boat_show_event_id (FK)
name (nullable)
width_ft (int) → total width of layout space
height_ft (int) → total height of layout space
grid_size (int, default 1)
scale (int, default 10)
meta (json, nullable)
timestamps
soft deletes
2. boat_show_layout_items

Represents a boat placed on the layout.

Fields:

id
layout_id (FK)
asset_unit_id (nullable FK)
inventory_unit_id (nullable FK)
name (string)
length_ft (decimal)
width_ft (decimal)
x (decimal) → position from left (feet)
y (decimal) → position from top (feet)
rotation (int, default 0) → allowed values: 0, 90, 180, 270
color (string, nullable)
z_index (int, default 0)
meta (json, nullable)
timestamps
Key Rules
1. Snapshot Data (CRITICAL)

When a boat is added:

Copy name, length, width into layout_items
Do NOT rely on the original boat record after placement
2. Rotation Logic
Rotation is stored as integer degrees: 0, 90, 180, 270
Effective dimensions:
0 or 180 → length = length_ft, width = width_ft
90 or 270 → length = width_ft, width = length_ft
3. Boundary Validation

A layout item is considered OUT OF BOUNDS if:

x < 0
y < 0
x + effective_length > layout.width_ft
y + effective_width > layout.height_ft

This should be:

Calculated on backend (validation)
Optionally stored in frontend only (no need to persist)
4. No Overlap (Optional v1, recommended v2)

Initially allow overlaps.
Later add collision detection.

API Design
GET /layouts/{id}

Returns:

{
  "id": 1,
  "width_ft": 50,
  "height_ft": 50,
  "items": [...]
}
POST /layouts

Create layout

PUT /layouts/{id}

Update layout dimensions

POST /layouts/{id}/items

Add item

PUT /layout-items/{id}

Update item (position, rotation, dimensions)

DELETE /layout-items/{id}

Remove item

BULK SAVE (IMPORTANT)

Preferred endpoint:

POST /layouts/{id}/sync

{
  "width_ft": 50,
  "height_ft": 50,
  "items": [
    {
      "id": 1,
      "name": "Boat A",
      "length_ft": 20,
      "width_ft": 8,
      "x": 2,
      "y": 2,
      "rotation": 90,
      "color": "#378ADD"
    }
  ]
}

Behavior:

Update existing items
Create new ones
Delete missing ones
Frontend Behavior (Canvas)
Coordinate System
Units are in feet
1 grid unit = 1 foot
Canvas uses scale multiplier (e.g., 10px per foot)
Boat Object Shape (Frontend)
{
  id,
  name,
  length_ft,
  width_ft,
  x,
  y,
  rotation,
  color
}
Core Interactions
Drag to move (snap to integer feet)
Rotate (90° increments)
Add boat via modal
Delete selected boat
Resize layout (width/height)
Backend Responsibilities
Validate:
numeric values
rotation allowed values
layout boundaries
Normalize:
round x/y to 2 decimals max
Handle bulk sync safely
Future Enhancements (Do NOT build yet)
Collision detection
Zones (dock sections, premium areas)
Auto-layout algorithm
Multi-layout per event (indoor/outdoor)
PDF export (Blade-based)
Real-time collaboration (WebSockets)
Non-Goals (for now)
No physics engine
No pixel-perfect snapping beyond grid
No 3D rendering
Summary

We are building a:

2D grid-based layout system
Using real-world units (feet)
With persistent backend storage
And a Vue/Inertia frontend canvas

Focus on:

simplicity
accuracy
clean data model

Avoid:

overengineering
premature optimization
tight coupling to boat records