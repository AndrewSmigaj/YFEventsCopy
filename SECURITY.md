# Security Guidelines for YFEvents

## ⚠️ IMPORTANT: API Keys and Secrets

### What NOT to do:
- ❌ Never commit real API keys to git
- ❌ Never put credentials in code files
- ❌ Never share API keys in chat/messages
- ❌ Never use production keys for development

### What TO do:
- ✅ Use `.env` files (which are gitignored)
- ✅ Use placeholder values in examples
- ✅ Create separate keys for dev/staging/production
- ✅ Restrict API keys to specific domains/IPs
- ✅ Rotate keys regularly

## Environment Setup

1. **Copy the example file:**
   ```bash
   cp .env.example .env
   ```

2. **Add your real values to `.env`:**
   ```env
   GOOGLE_MAPS_API_KEY=your_actual_api_key_here
   DB_PASS=your_actual_password_here
   ```

3. **Verify `.env` is in `.gitignore`** (it is!)

## Google Maps API Security

1. **Restrict your API key** in Google Cloud Console:
   - HTTP referrers: `yourdomain.com/*`
   - IP addresses: Your server IP only

2. **Enable only needed APIs:**
   - Maps JavaScript API
   - Places API  
   - Geocoding API

3. **Set usage quotas** to prevent abuse

## Database Security

- Use strong passwords
- Create dedicated database user (not root)
- Limit database user permissions to specific database only
- Use SSL connections if possible

## Production Deployment

- [ ] Use environment variables (not `.env` files)
- [ ] Enable HTTPS only
- [ ] Set secure session cookies
- [ ] Use proper firewall rules
- [ ] Regular security updates
- [ ] Monitor access logs

## Incident Response

If API keys are accidentally exposed:
1. **Immediately revoke** the exposed key
2. **Generate new key** with proper restrictions
3. **Update all systems** with new key
4. **Review logs** for unauthorized usage
5. **Document the incident**

## Contact

For security issues, contact: [your-security-email]