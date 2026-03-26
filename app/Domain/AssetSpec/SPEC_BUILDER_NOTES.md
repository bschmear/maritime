Spec Builder UI – Requirements & Behavior
1. Prebuilt Spec Library
Display all AssetSpecDefinition entries in a library/grid/list.
Show key details: Label, Group, Type, Unit, Asset Types, Filterable, Required.
Include search/filter by group, type, unit, or asset type.
Allow drag-and-drop or checkbox selection to add specs to an asset type or asset.
2. Selected Spec Panel
Shows the currently active specs for the selected asset type or individual asset.
Specs can be reordered using drag-and-drop (respecting position).
Required specs (like length and width for boats) are locked and always visible.
Optional specs can be toggled on/off (removed if not needed).
3. Add New Spec
Users can create a custom spec with:
Key (unique identifier)
Label
Group
Type (number, text, boolean, select)
Unit (optional)
Selectable options (for select type)
Filterable toggle
Required toggle
Asset type(s) the spec applies to
New specs can be saved as reusable templates in the library.
4. Asset Type Toggle
Each spec can indicate which asset types it applies to.
Allow filtering library by asset type.
When adding a spec to an asset, only show specs relevant to that type.
5. Metric/Imperial Support
Numeric fields should support both unit systems.
Users can toggle the display units in the preview for selected specs.
6. Live Preview
As users select or add specs, show a preview of how these specs would look in an asset form.
Include required/optional indicators, units, and field types.
Optional: show example values or default placeholder values.
7. Persisting User Choices
Selected specs for an asset type are stored in a pivot table (e.g., asset_type_spec_definitions or just asset_spec_values when applied to assets).
Custom specs created by users are added to the main spec library for reuse.
Suggested UI Layout
+--------------------------------------------------------------+
|                  Asset Spec Builder                          |
+----------------------+------------------------+--------------+
|  Spec Library        |  Selected Specs Panel  | Preview      |
|----------------------|------------------------|--------------|
| [Search/Filters]     | [Asset Type: Boat]    | Form Preview |
| Group | Type | Label  | Spec Label | Req/Opt  | length [ft] |
| [ ] Checkbox/Drag    | [x] Length             | width [ft]  |
| [ ] Checkbox/Drag    | [x] Width              | dead rise   |
| ...                  | [ ] Tube Diameter      | ...         |
| Add New Spec Button  |                        |              |
+----------------------+------------------------+--------------+
Technical Notes
Frontend: Vue 3 + Composition API
Spec Library Component
Selected Specs Component
Add Spec Modal/Drawer
Live Preview Form Component
Backend: Laravel
AssetSpecDefinition API for fetching prebuilt specs
AssetSpecValue API for saving per-asset specs
Optional pivot for AssetType → AssetSpecDefinition
Validation:
Required specs cannot be removed
Unique key enforcement for new custom specs
Extras:
Drag-and-drop reordering
Multi-asset-type selection for a single spec
Tooltip for units and type hints
