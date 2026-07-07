#!/bin/bash
# PVE Panel - Deploy Admin Pages
# Run this on the server or from a machine with SSH access:
# ssh root@pve.ypvps.com 'bash -s' < deploy_admin.sh

SERVER="root@154.9.237.203"
DEPLOY_PATH="/var/www/pve-panel"
LOCAL_BASE="C:/Users/admin/WorkBuddy/2026-07-01-12-05-38/pve-panel"

echo "=== PVE Panel Admin Pages Deployment ==="

# Files to deploy
FILES=(
  "public/js/api.js"
  "public/views/auth/login.html"
  "public/views/admin/index.html"
  "public/views/admin/nodes.html"
  "public/views/admin/users.html"
  "public/views/admin/products.html"
  "public/views/admin/vms.html"
  "public/views/admin/orders.html"
  "public/views/admin/announcements.html"
  "public/views/admin/coupons.html"
  "public/views/admin/settings.html"
  "public/views/admin/tickets-admin.html"
  "public/views/admin/verify.html"
  "public/views/admin/backup.html"
)

for file in "${FILES[@]}"; do
  echo "Deploying: $file"
  scp -o StrictHostKeyChecking=no "$LOCAL_BASE/$file" "$SERVER:$DEPLOY_PATH/$file"
done

# Fix permissions
ssh "$SERVER" "chown -R www-data:www-data $DEPLOY_PATH/public && chmod -R 755 $DEPLOY_PATH/public"

echo "=== Deployment Complete ==="
echo ""
echo "Test these pages:"
echo "  Login: https://pve.ypvps.com/views/auth/login.html"
echo "  Dashboard: https://pve.ypvps.com/views/admin/index.html"
echo "  Nodes: https://pve.ypvps.com/views/admin/nodes.html"
echo "  Users: https://pve.ypvps.com/views/admin/users.html"
echo "  Products: https://pve.ypvps.com/views/admin/products.html"
echo "  VMs: https://pve.ypvps.com/views/admin/vms.html"
echo "  Orders: https://pve.ypvps.com/views/admin/orders.html"
