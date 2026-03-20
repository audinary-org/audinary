#!/usr/bin/env node

const fs = require('fs');
const { execSync } = require('child_process');

// Get bump type from command line args
const bumpType = process.argv[2] || 'patch'; // major, minor, patch

if (!['major', 'minor', 'patch'].includes(bumpType)) {
  console.error('❌ Invalid bump type. Use: major, minor, or patch');
  process.exit(1);
}

console.log(`🔄 Bumping ${bumpType} version...`);

try {
  // Use npm version to bump the root package.json
  execSync(`npm version ${bumpType} --no-git-tag-version`, { stdio: 'inherit' });
  
  // Sync to all other files
  execSync('npm run version:sync', { stdio: 'inherit' });
  
  // Get new version
  const rootPackage = JSON.parse(fs.readFileSync('package.json', 'utf8'));
  const newVersion = rootPackage.version;
  
  console.log(`✅ Version bumped to ${newVersion}`);
  console.log('📝 Don\'t forget to commit and tag:');
  console.log(`   git add -A`);
  console.log(`   git commit -m "chore: bump version to ${newVersion}"`);
  console.log(`   git tag v${newVersion}`);
  
} catch (error) {
  console.error('❌ Version bump failed:', error.message);
  process.exit(1);
}