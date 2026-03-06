# Git Setup & Push Instructions

## Initial Setup (first time only)

From the plugin directory:

```bash
cd wp-content/plugins/posthog-for-wp

# Initialize git (if not already)
git init

# Add remote
git remote add origin https://github.com/ashawkat/posthog-for-wordpress.git

# Add all files
git add .

# Commit
git commit -m "Initial release v1.0.0"

# Push (use main or master depending on your default branch)
git branch -M main
git push -u origin main
```

## Push Updates

```bash
cd wp-content/plugins/posthog-for-wp

git add .
git commit -m "Your commit message"
git push origin main
```

## If repo already has content

If the remote already has commits (e.g. README from GitHub):

```bash
git pull origin main --allow-unrelated-histories
# Resolve any conflicts, then:
git push origin main
```

## Clone for development

```bash
git clone https://github.com/ashawkat/posthog-for-wordpress.git wp-content/plugins/posthog-for-wp
```
