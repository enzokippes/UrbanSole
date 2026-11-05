import { spawn } from 'node:child_process';
import { realpathSync } from 'node:fs';
import { join } from 'node:path';

const realCwd = realpathSync(process.cwd());
process.chdir(realCwd);

const ngBin = join(realCwd, 'node_modules', '@angular', 'cli', 'bin', 'ng.js');
const args = [ngBin, ...process.argv.slice(2)];

const child = spawn(process.execPath, args, {
  cwd: realCwd,
  stdio: 'inherit',
});

child.on('exit', (code) => {
  process.exit(code ?? 0);
});
