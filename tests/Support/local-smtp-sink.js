const { createServer } = require('node:net');
const { appendFileSync, mkdirSync, writeFileSync } = require('node:fs');
const path = require('node:path');

const outFile = process.argv[2];

if (!outFile) {
  throw new Error('An output file path is required.');
}

mkdirSync(path.dirname(outFile), { recursive: true });
writeFileSync(outFile, '');

const server = createServer((socket) => {
  let buffer = '';
  let dataMode = false;
  let messageBuffer = '';

  socket.write('220 localhost SMTP ready\r\n');

  socket.on('data', (chunk) => {
    buffer += chunk.toString('utf8');

    while (true) {
      if (dataMode) {
        const endOfData = buffer.indexOf('\r\n.\r\n');
        if (endOfData === -1) {
          break;
        }

        messageBuffer += buffer.slice(0, endOfData);
        appendFileSync(outFile, `${JSON.stringify({ message: messageBuffer })}\n`);
        messageBuffer = '';
        buffer = buffer.slice(endOfData + 5);
        dataMode = false;
        socket.write('250 2.0.0 queued\r\n');
        continue;
      }

      const lineEnd = buffer.indexOf('\r\n');
      if (lineEnd === -1) {
        break;
      }

      const line = buffer.slice(0, lineEnd);
      buffer = buffer.slice(lineEnd + 2);
      const upper = line.toUpperCase();

      if (upper.startsWith('EHLO') || upper.startsWith('HELO')) {
        socket.write('250-localhost\r\n250 OK\r\n');
        continue;
      }

      if (upper.startsWith('MAIL FROM') || upper.startsWith('RCPT TO') || upper.startsWith('RSET')) {
        socket.write('250 OK\r\n');
        continue;
      }

      if (upper === 'DATA') {
        dataMode = true;
        socket.write('354 End data with <CR><LF>.<CR><LF>\r\n');
        continue;
      }

      if (upper.startsWith('QUIT')) {
        socket.write('221 Bye\r\n');
        socket.end();
        break;
      }

      socket.write('250 OK\r\n');
    }
  });
});

server.listen(0, '127.0.0.1', () => {
  process.stdout.write(`${JSON.stringify({ port: server.address().port })}\n`);
});

function shutdown() {
  server.close(() => process.exit(0));
}

process.on('SIGTERM', shutdown);
process.on('SIGINT', shutdown);
