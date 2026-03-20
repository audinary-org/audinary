#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

// Read root package.json
const rootPackage = JSON.parse(fs.readFileSync('package.json', 'utf8'));
const version = rootPackage.version;

console.log(`Syncing version ${version}...`);

// Update client package.json
const clientPackagePath = 'client/package.json';
const clientPackage = JSON.parse(fs.readFileSync(clientPackagePath, 'utf8'));
clientPackage.version = version;
fs.writeFileSync(clientPackagePath, JSON.stringify(clientPackage, null, 2) + '\n');

// Update server VERSION file
const serverVersionPath = 'server/VERSION';
fs.writeFileSync(serverVersionPath, version + '\n');

console.log('✅ Version synced across all packages');