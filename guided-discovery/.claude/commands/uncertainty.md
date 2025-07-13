# Explore Uncertainty

Deep dive into a specific uncertainty to understand what needs to be resolved.

```bash
UNCERTAINTY_ID="$ARGUMENTS"

# Find active context
CONTEXT_FILE=$(ls -t /mnt/d/YFEventsCopy/guided-discovery/contexts/active/*.json 2>/dev/null | head -1)

if [ -z "$CONTEXT_FILE" ]; then
  echo "❌ No active discovery context found"
  echo ""
  echo "Start a new discovery with: /discover \"your task description\""
  exit 1
fi

# If no ID provided, list all uncertainties
if [ -z "$UNCERTAINTY_ID" ]; then
  echo "## 🔍 All Uncertainties"
  echo ""
  echo "Specify an uncertainty ID to explore:"
  echo ""
  echo "**Blocking**:"
  echo "- AUTH-001: Current authentication implementation"
  echo "- SEC-001: Security requirements"
  echo ""
  echo "**High Priority**:"
  echo "- TECH-001: Technology stack details"
  echo "- ARCH-001: Architecture patterns"
  echo ""
  echo "Usage: \`/uncertainty AUTH-001\`"
  exit 0
fi
```

## 🔍 Uncertainty: $UNCERTAINTY_ID

```bash
# For demonstration, handle common uncertainty IDs
# In production, would parse from context JSON

case "$UNCERTAINTY_ID" in
  "AUTH-001")
    DESC="Current authentication implementation pattern"
    IMPACT="Cannot design JWT solution without understanding existing auth"
    STATUS="partial"
    CONFIDENCE="40"
    PRIORITY="blocking"
    ;;
  "AUTH-002")
    DESC="Session persistence and management approach"
    IMPACT="Determines token storage and session migration strategy"
    STATUS="unresolved"
    CONFIDENCE="0"
    PRIORITY="blocking"
    ;;
  "SEC-001")
    DESC="Security requirements and compliance needs"
    IMPACT="Shapes token validation, storage, and audit approach"
    STATUS="partial"
    CONFIDENCE="30"
    PRIORITY="high"
    ;;
  "ARCH-001")
    DESC="Overall architecture pattern and style"
    IMPACT="Affects all implementation decisions"
    STATUS="resolved"
    CONFIDENCE="85"
    PRIORITY="high"
    ;;
  "TECH-001")
    DESC="Technology stack and version constraints"
    IMPACT="Determines available libraries and patterns"
    STATUS="partial"
    CONFIDENCE="60"
    PRIORITY="high"
    ;;
  *)
    echo "❌ Uncertainty '$UNCERTAINTY_ID' not found"
    echo ""
    echo "Use \`/uncertainty\` to list all uncertainties"
    exit 1
    ;;
esac

# Display uncertainty details
echo "**Description**: $DESC"
echo "**Priority**: $PRIORITY"
echo "**Status**: $STATUS ($CONFIDENCE% resolved)"
echo "**Impact**: $IMPACT"
```

### 📊 Resolution Progress

```bash
# Visual progress bar
printf "Progress: "
BARS=$((CONFIDENCE / 5))
for i in $(seq 1 20); do
  if [ $i -le $BARS ]; then printf "█"; else printf "░"; fi
done
echo " $CONFIDENCE%"
echo ""

# Status indicator
if [ "$STATUS" = "resolved" ]; then
  echo "✅ **This uncertainty is resolved!**"
elif [ "$STATUS" = "partial" ]; then
  echo "🔄 **Partially resolved** - more discovery needed"
else
  echo "❌ **Unresolved** - needs investigation"
fi
```

### 📋 Resolution Criteria

What we need to discover to fully resolve this uncertainty:

```bash
case "$UNCERTAINTY_ID" in
  "AUTH-001")
    echo "**Required**:"
    echo "- [x] Authentication middleware location"
    echo "- [x] Auth strategy type (session/JWT/OAuth)"
    echo "- [ ] Session storage mechanism"
    echo "- [ ] User model structure"
    echo ""
    echo "**Optional**:"
    echo "- [ ] Permission/role system"
    echo "- [ ] Auth event hooks"
    echo "- [ ] Remember me functionality"
    ;;
  "SEC-001")
    echo "**Required**:"
    echo "- [x] Data sensitivity classification"
    echo "- [ ] Compliance requirements (GDPR, HIPAA, etc)"
    echo "- [ ] Threat model"
    echo "- [ ] Current security measures"
    echo ""
    echo "**Optional**:"
    echo "- [ ] Audit requirements"
    echo "- [ ] Encryption standards"
    echo "- [ ] Rate limiting needs"
    ;;
  "ARCH-001")
    echo "**Required**:"
    echo "- [x] Architecture style (monolith/microservices)"
    echo "- [x] Primary framework"
    echo "- [x] Request flow pattern"
    echo "- [x] Component organization"
    echo ""
    echo "**All criteria met!** ✅"
    ;;
esac
```

### 🔗 Dependencies

```bash
case "$UNCERTAINTY_ID" in
  "AUTH-001")
    echo "**Depends on**: None (root uncertainty)"
    echo ""
    echo "**Enables**:"
    echo "- AUTH-002: Session management approach"
    echo "- SEC-001: Security requirements"
    echo "- IMPL-001: Implementation details"
    ;;
  "AUTH-002")
    echo "**Depends on**:"
    echo "- AUTH-001: Must understand current auth first"
    echo ""
    echo "**Enables**:"
    echo "- TOKEN-001: Token storage strategy"
    echo "- MIG-001: Migration approach"
    ;;
  "SEC-001")
    echo "**Depends on**:"
    echo "- AUTH-001: Current auth implementation"
    echo ""
    echo "**Enables**:"
    echo "- TOKEN-002: Token security measures"
    echo "- AUDIT-001: Audit trail requirements"
    ;;
esac
```

### 🎯 Suggested Actions

Based on the current resolution status:

```bash
if [ "$CONFIDENCE" -lt 30 ]; then
  echo "**Priority: HIGH** - This uncertainty is blocking progress"
  echo ""
  echo "1. **Run targeted discovery chain**:"
  case "$UNCERTAINTY_ID" in
    "AUTH-001"|"AUTH-002")
      echo "   \`/chain auth_discovery\`"
      echo "   This chain specifically targets authentication uncertainties"
      ;;
    "SEC-001")
      echo "   \`/chain security_analysis\`"
      echo "   This chain explores security requirements"
      ;;
    "TECH-001")
      echo "   \`/chain tech_stack_analysis\`"
      echo "   This chain identifies technology constraints"
      ;;
  esac
  echo ""
  echo "2. **Or run specific prompts**:"
  echo "   - \`/prompt auth_pattern_finder\`"
  echo "   - \`/prompt session_analyzer\`"
  
elif [ "$CONFIDENCE" -lt 80 ]; then
  echo "**Making progress** - targeted investigation needed"
  echo ""
  echo "Focus on unresolved criteria:"
  case "$UNCERTAINTY_ID" in
    "AUTH-001")
      echo "- Investigate session storage: \`/prompt session_storage_analyzer\`"
      echo "- Examine user model: \`/prompt user_model_explorer\`"
      ;;
    "SEC-001")
      echo "- Check compliance needs: \`/prompt compliance_requirements\`"
      echo "- Analyze threats: \`/prompt threat_model_analyzer\`"
      ;;
  esac
  
else
  echo "**Nearly resolved!** Just a few details remaining"
  echo ""
  echo "Consider this resolved if you have enough information to proceed."
  echo "Or do final verification with targeted prompts."
fi
```

### 💡 Context & Notes

```bash
# Show any additional context or discoveries related to this uncertainty
case "$UNCERTAINTY_ID" in
  "AUTH-001")
    echo "**Previous discoveries**:"
    echo "- Found middleware at /middleware/auth.js"
    echo "- Identified passport.js usage"
    echo "- Session-based authentication confirmed"
    echo ""
    echo "**Still unknown**:"
    echo "- Exact session configuration"
    echo "- User model schema"
    echo "- Permission system details"
    ;;
  "SEC-001")
    echo "**Previous discoveries**:"
    echo "- Application handles user data"
    echo "- No explicit security policy found yet"
    echo ""
    echo "**Key questions**:"
    echo "- Is GDPR compliance required?"
    echo "- What data is considered sensitive?"
    echo "- Are there industry-specific requirements?"
    ;;
esac
```

### 📚 Related Resources

```bash
echo "**Related uncertainties**:"
case "$UNCERTAINTY_ID" in
  "AUTH-001")
    echo "- AUTH-002: Session management"
    echo "- SEC-001: Security requirements"
    echo "- USER-001: User model details"
    ;;
  "SEC-001")
    echo "- AUTH-001: Authentication implementation"
    echo "- DATA-001: Data sensitivity"
    echo "- COMP-001: Compliance needs"
    ;;
esac

echo ""
echo "**Useful commands**:"
echo "- View all uncertainties: \`/context uncertainties\`"
echo "- Check overall progress: \`/context status\`"
echo "- Run discovery chain: \`/chain [chain_name]\`"
```