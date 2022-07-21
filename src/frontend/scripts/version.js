const fs = require("fs-extra");

let version = process.env.RELEASE_VERSION;

const backendBuildConfigPath = '../../app/config/config_build.yml';
if (!version && fs.existsSync(backendBuildConfigPath)) {
    version = fs.readFileSync(backendBuildConfigPath, 'utf8').match(/version: (.+)/)[1].trim();
}

if (!version) {
    version = 'UNKNOWN';
}

if (version.charAt(0) === 'v') {
    version = version.substr(1);
}

module.exports = {version};
