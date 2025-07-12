# Claude Development Rules

## Working Style

1. **Wait for explicit direction** - Never rush into programming or fixing things, even when in auto-accept mode. The user will tell you when to get to work and what to work on. Always:
   - Analyze and understand the request first
   - Ask clarifying questions if needed
   - Present a plan or approach before implementing
   - Wait for the user's go-ahead before making changes

## Core Principles

1. **Never create copies of files to implement changes** - Use version control (git) to track changes
2. **Modify files in place** when making updates
3. **Use git history** to track changes, not file copies
4. **Single source of truth** - One file for one purpose

## Code Quality

5. **Follow existing patterns** - Match the code style and architecture of the project
6. **Clean Architecture adherence** - Respect layer boundaries and dependencies
7. **Configuration over hardcoding** - Use config files and environment variables
8. **Validate inputs** - Always validate user input and configuration
9. **Error handling** - Provide clear error messages and handle failures gracefully

## Deployment & DevOps

10. **Idempotent operations** - Scripts should be safe to run multiple times
11. **No interactive prompts in production** - Use configuration files
12. **Security first** - Never store passwords in plain text, validate all inputs
13. **Backup before destructive operations** - Always preserve ability to rollback

## Documentation

14. **Keep documentation in sync** - Update docs when changing functionality
15. **Comment why, not what** - Focus on explaining decisions, not obvious code
16. **Update CLAUDE.md** for significant changes that affect AI assistance

## Testing

17. **Test before committing** - Verify changes work as expected
18. **Consider edge cases** - Handle missing files, bad inputs, network failures
19. **Preserve existing tests** - Update tests when changing functionality

## Version Control

20. **Meaningful commit messages** - Describe the why and impact of changes
21. **Small, focused commits** - One logical change per commit
22. **Never commit secrets** - Use .env files and keep them out of git