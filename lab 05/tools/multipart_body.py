# Будує сире тіло multipart/form-data (межа ----lab05boundary) для двох випадків, які curl -F не відтворює:
#   nofile  — поле attachment без вибраного файла (filename=""), як надсилає браузер;
#   partial — тіло обривається посеред файла без завершальної межі (UPLOAD_ERR_PARTIAL).
# Запуск: python tools/multipart_body.py nofile|partial файл-результату "текст повідомлення"
import sys

mode, target, message = sys.argv[1:4]
boundary = b'----lab05boundary'
crlf = b'\r\n'
parts = []
for key, value in [('name', 'Ґанна Їжак'), ('email', 'ganna@example.com'), ('message', message)]:
    parts.append(b'--' + boundary + crlf + b'Content-Disposition: form-data; name="' + key.encode() + b'"' + crlf + crlf
                 + value.encode('utf-8') + crlf)
if mode == 'nofile':
    parts.append(b'--' + boundary + crlf + b'Content-Disposition: form-data; name="attachment"; filename=""' + crlf
                 + b'Content-Type: application/octet-stream' + crlf + crlf + crlf + b'--' + boundary + b'--' + crlf)
else:
    parts.append(b'--' + boundary + crlf + b'Content-Disposition: form-data; name="attachment"; filename="cut.txt"' + crlf
                 + b'Content-Type: text/plain' + crlf + crlf + b'x' * 5000)
with open(target, 'wb') as out:
    out.write(b''.join(parts))
