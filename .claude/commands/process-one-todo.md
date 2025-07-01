# Process One Todo Item

You are an expert project manager and systems analyst. Process EXACTLY ONE todo item using this focused methodology. This command is designed to prevent rushing ahead by focusing on a single item.

## 🛑 STOP AND READ 🛑
This command processes ONE item only. After completing all phases for this ONE item, STOP.

## Phase 1: Architecture Review (MANDATORY FIRST STEP)
**Read architecture.yaml before anything else**
- Load the current system architecture
- Understand existing patterns and components
- Note what already exists

## Phase 2: Single Item Analysis
For the ONE todo item provided:

1. **Parse the requirement**
   - What exactly needs to be done?
   - What are the success criteria?
   - What constraints exist?

2. **Anti-reinvention check**
   - Does this already exist in the codebase?
   - Are there existing utilities to leverage?
   - What patterns should I follow?

## Phase 3: Rough Draft (MANDATORY)
**You MUST write actual code here, not just descriptions**

<thinking>
Write your FIRST INSTINCT implementation:
- Real code with actual logic
- Don't worry about perfection
- Include the obvious approach
- This is what you'd naturally code first

Example:
```python
class MemoryEfficientStorage:
    def __init__(self):
        self.data = {}  # Still using dict!
    
    def store(self, key, value):
        self.data[key] = value  # Not actually memory efficient
```
</thinking>

## Phase 4: Self-Review (MANDATORY)
**Review your rough draft for these issues:**

❌ **Reinvention**: Am I reimplementing existing functionality?
❌ **Design flaws**: Is this good architecture?
❌ **Correctness**: Will this actually work?
❌ **Integration**: Does this fit with existing code?
❌ **Complexity**: Is this unnecessarily complex?
❌ **Edge cases**: What did I miss?

**Document ALL problems found.**

## Phase 5: Improved Plan
Based on your self-review, present the FIXED version:
- Address every issue from self-review
- Use existing components instead of reinventing
- Ensure good design principles
- Keep it simple

## Phase 6: 🛑 WAIT FOR APPROVAL 🛑
**DO NOT PROCEED TO IMPLEMENTATION**

Present your improved plan and ask:
"Should I proceed with this implementation?"

Only continue if user explicitly says:
- "continue"
- "proceed"  
- "implement"
- "yes"
- "approved"

## Phase 7: Implementation (ONLY AFTER APPROVAL)
If approved:
1. Implement the reviewed solution
2. Test as you go
3. Document decisions

## Phase 8: Verification
After implementation:
1. Show what was created/modified
2. Confirm it works as intended
3. Note any deviations from plan

## Output Format

### 📋 Todo Item: [The one item]

**Step 1: Architecture Review**
- Key existing components: [list]
- Patterns to follow: [list]
- Potential reuse: [list]

**Step 2: Anti-Reinvention Check**
- Existing solutions found: [list]
- Why they do/don't work: [explanation]

**Step 3: Initial Approach** (Rough Draft)
<thinking>
[Actual code here - your first instinct]
</thinking>

**Step 4: Self-Review Results**
Problems found:
- ❌ [Issue 1]
- ❌ [Issue 2]
- ❌ [Issue 3]

**Step 5: Improved Plan**
```python
[The corrected, improved implementation plan]
```

Key improvements:
- ✅ [How you fixed issue 1]
- ✅ [How you fixed issue 2]
- ✅ [How you fixed issue 3]

### 🛑 APPROVAL CHECKPOINT 🛑
**Should I proceed with this implementation?**
[WAIT HERE - Do not continue without explicit approval]

---

### ✅ Implementation (Only shown after approval)
[Actual implementation]

### 📊 Verification
[Results and confirmation]

---