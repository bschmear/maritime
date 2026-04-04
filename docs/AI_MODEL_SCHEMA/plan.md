1. Core Mental Model (Lock This In)

You now have 3 layers:

✅ Static truth (your files)
models.json → list of selectable models
meta.json → enriched structured data
✅ Runtime resolution
User selects:
make
model
System attempts:
→ meta[model_id]
✅ AI fallback (only when missing)
Generate:
full model object
OR variants structure
Then:
hydrate UI
persist to meta.json
2. Canonical Data Contract (VERY IMPORTANT)

Before touching AI, you need a strict schema contract.

This is your single source of truth shape:

🔹 Model Meta Schema (no variants)
{
  "series": "string",
  "type_display": "string",
  "boat_type_key": "runabouts",
  "description": "string",
  "specifications": {
    "length": "string|null",
    "width": "string|null",
    "height": "string|null",
    "weight": "string|null",
    "capacity_persons": "number|null",
    "max_hp": "string|null",
    "fuel_capacity": "string|null"
  },
  "features": ["string"]
}
🔹 Model Meta Schema (WITH variants)
{
  "series": "string",
  "type_display": "string",
  "boat_type_key": "tenders",
  "description": "string",
  "features": ["string"],
  "variants": [
    {
      "id": "string",
      "name": "string",
      "specifications": {
        "length": "string|null",
        "width": "string|null",
        "height": "string|null",
        "weight": "string|null",
        "capacity_persons": "number|null",
        "max_hp": "string|null",
        "fuel_capacity": "string|null"
      }
    }
  ]
}
3. AI Schema (What You Send to OpenAI)

You should NOT let AI freestyle this.

Use strict JSON schema prompting.

🔥 AI Prompt Strategy
System Prompt
You are a marine data normalization engine.

You must return ONLY valid JSON matching the provided schema.

Rules:
- No extra fields
- No missing required fields
- Use null when unknown
- Use consistent units (feet/inches, lbs, gallons, hp)
- Do not hallucinate specifications — prefer null if unsure
- Variants should only be included if the model is known to have size or trim variations
🔥 User Prompt (dynamic)
{
  "task": "generate_boat_model_metadata",
  "make": "Walker Bay",
  "model": "G15",
  "category": "inflatable",
  "expected_schema": "model_meta"
}
🔥 AI Response (STRICT)

You enforce one of two responses:

Option A (no variants)
{
  "series": "...",
  "type_display": "...",
  "boat_type_key": "...",
  "description": "...",
  "specifications": {...},
  "features": [...]
}
Option B (with variants)
{
  "series": "...",
  "type_display": "...",
  "boat_type_key": "...",
  "description": "...",
  "features": [...],
  "variants": [...]
}
4. Backend Flow (Step-by-Step)
🔹 Step 1: User selects Make + Model
$make = 'walker-bay';
$model = 'g15';

$meta = MetaLoader::for($make);

if (isset($meta[$model])) {
    return $meta[$model]; // ✅ instant autofill
}
🔹 Step 2: No match → show AI button

Frontend:

if (!metaExists) {
  showAutoFillAI = true;
}
🔹 Step 3: User clicks "Auto Fill with AI"

Call backend:

POST /api/boats/auto-fill

Payload:

{
  "make": "walker-bay",
  "model": "g15"
}
🔹 Step 4: Backend AI Service
$response = OpenAI::chat()->create([
    'model' => 'gpt-5',
    'response_format' => ['type' => 'json_object'],
    'messages' => [...]
]);
🔹 Step 5: Validate BEFORE saving

Do NOT trust AI blindly

$data = json_decode($response, true);

Validator::make($data, [
    'series' => 'required|string',
    'type_display' => 'required|string',
    'boat_type_key' => ['required', Rule::in(BoatType::values())],
    'description' => 'required|string',
    'features' => 'array',

    // conditional
    'specifications' => 'array',
    'variants' => 'array'
]);
🔹 Step 6: Normalize (CRITICAL STEP)

Examples:

"21 ft" → "21' 0\""
"225 HP" → "225hp"
"12 people" → 12

You want a SpecificationNormalizer class

🔹 Step 7: Persist to meta.json
$meta[$model] = $data;

file_put_contents(
    app_path("AssetInformation/{$make}/meta.json"),
    json_encode($meta, JSON_PRETTY_PRINT)
);
🔹 Step 8: Return to frontend
{
  "data": { ...normalizedMeta }
}

Frontend:

hydrate form
generate variant rows if needed
5. Frontend Behavior
When data returned:
If variants exists:
render variant selector
auto-create rows per variant
Else:
fill single spec form
6. Smart Enhancements (HIGH VALUE)
🔥 1. Cache AI responses (before writing)

Prevent duplicate calls during race conditions.

🔥 2. Add confidence scoring

AI can return:

"_confidence": "low"

If low:
→ flag UI: “Verify specs”

🔥 3. Admin review queue (optional but powerful)

Instead of writing directly:

save to meta_pending.json
admin approves → merges into meta.json
🔥 4. Versioning meta.json
"_generated_at": "2026-04-04",
"_source": "ai"
7. Key Design Decisions You Got Right

This is important:

✅ File-based structure → fast + portable
✅ Model key system → stable lookups
✅ Variant separation → scalable
✅ AI fallback → fills gaps over time
✅ Persisting AI → system improves itself

8. One Critical Warning

Do NOT:

Let AI decide IDs
Let AI invent variants blindly
Let AI overwrite existing meta

Always:

if (!isset($meta[$model])) {
    // only then write
}
9. Clean Folder Structure
AssetInformation/
  walker-bay/
    models.json
    meta.json
  ab-inflatables/
    models.json
    meta.json
10. Final Architecture Summary

User Flow

Select Make → Select Model
        ↓
Check meta.json
   ↓         ↓
 Found     Not Found
  ↓           ↓
Autofill   Show AI Button
               ↓
        Generate + Validate + Normalize
               ↓
          Save to meta.json
               ↓
           Autofill UI