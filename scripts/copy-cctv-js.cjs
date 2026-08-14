const fs = require('fs');
const path = require('path');

const sourceDir = path.join(__dirname, '..', 'resources', 'js', 'modules', 'cctv');
const targetDir = path.join(__dirname, '..', 'public', 'assets', 'js', 'modules', 'cctv');

fs.mkdirSync(targetDir, { recursive: true });

for (const file of fs.readdirSync(sourceDir)) {
    if (!file.endsWith('.js')) {
        continue;
    }

    fs.copyFileSync(path.join(sourceDir, file), path.join(targetDir, file));
}

console.log('CCTV JS modules copied to public/assets/js/modules/cctv/');
