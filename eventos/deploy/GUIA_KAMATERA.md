# Publicar en Kamatera (app PHP + API Node.js + MySQL en un solo servidor)

Esquema final:

```
Internet --HTTPS--> Nginx (80/443) --> PHP-FPM (app "eventos")
                                          |
                                          +--> API Node.js en 127.0.0.1:3000 (solo interna) --> MySQL
```

La API **no** se expone a internet: solo la app PHP la llama por `127.0.0.1:3000`.
No suba la carpeta `deploy` ni `info.php` al servidor.

## 1. Crear el servidor en Kamatera
1. Entre a console.kamatera.com > *Create New Server*.
2. Imagen: **Ubuntu Server 22.04 o 24.04 LTS (64 bit)**.
3. Datacenter: el más cercano a sus usuarios.
4. Tamaño suficiente para empezar: 1-2 vCPU, **2 GB de RAM**, 20-30 GB de disco SSD.
5. Red: una IP pública. Defina la contraseña de root (larga) o su llave SSH.
6. Cree el servidor y anote la **IP pública**.

(Precios y prueba gratuita: confírmelos en el sitio de Kamatera.)

## 2. Apuntar el dominio (GoDaddy)
En GoDaddy > su dominio > *DNS*: registros **A**
- `@` -> IP del servidor
- `www` -> IP del servidor

Puede tardar unos minutos en propagarse.

## 3. Preparar el servidor
Desde PowerShell en su PC: `ssh root@IP_DEL_SERVIDOR`. Luego en el servidor:

```bash
apt update && apt upgrade -y

# Firewall: solo SSH, HTTP y HTTPS (el puerto 3000 y MySQL quedan cerrados)
ufw allow OpenSSH && ufw allow 80 && ufw allow 443 && ufw --force enable

# Nginx, PHP y MySQL
apt install -y nginx mysql-server php-fpm php-mysql php-curl php-mbstring php-xml unzip certbot python3-certbot-nginx

# Node.js 20 + PM2
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs
npm install -g pm2
```

Tamaño de subida en PHP (imágenes de boleta, máx. 2 MB). Edite
`/etc/php/8.3/fpm/php.ini` (use su versión: `php -v`) y deje:

```
upload_max_filesize = 3M
post_max_size = 4M
```
y `systemctl restart php8.3-fpm`.

## 4. Base de datos
```bash
mysql_secure_installation          # responda Y a todo y ponga clave a root
mysql -u root -p
```
Dentro de MySQL (cambie la clave):
```sql
CREATE USER 'api_user'@'localhost' IDENTIFIED BY 'CLAVE_LARGA_Y_UNICA';
FLUSH PRIVILEGES;
EXIT;
```
Después de subir la API (paso 5), cargue el esquema (crea la base `api_boletas`, los roles y las tablas):
```bash
mysql -u root -p < /var/www/api/schema.sql
mysql -u root -p -e "GRANT ALL PRIVILEGES ON api_boletas.* TO 'api_user'@'localhost'; FLUSH PRIVILEGES;"
```

## 5. Subir y arrancar la API
En **su PC** (PowerShell), sin `node_modules` ni `.env`:
```powershell
cd C:\APIS
tar -czf api.tgz --exclude=node_modules --exclude=.env API_LANZAMIENTO
scp api.tgz root@IP_DEL_SERVIDOR:/root/
```
En el **servidor**:
```bash
mkdir -p /var/www && cd /var/www
tar -xzf /root/api.tgz && mv API_LANZAMIENTO api
cd api && npm install --omit=dev
cp /ruta/a/env.produccion.ejemplo .env     # o créelo con nano; vea deploy/env.produccion.ejemplo
nano .env                                  # ponga la clave de MySQL y secretos nuevos (openssl rand -hex 32)

# (ahora cargue el esquema del paso 4 si aún no lo hizo)
pm2 start src/app.js --name api-eventos
pm2 save && pm2 startup                    # ejecute el comando que le muestre
curl -s http://127.0.0.1:3000/api/roles    # debe responder 401 (pide token): la API está viva
```
Cree el usuario administrador (idrol 1 = rol admin):
```bash
npm run crear-usuario -- admin SuClaveFuerte123 1
```

## 6. Subir la app PHP
En **su PC**, sin `info.php` ni `deploy`:
```powershell
cd C:\xampp\htdocs
tar -czf eventos.tgz --exclude=info.php --exclude=deploy eventos
scp eventos.tgz root@IP_DEL_SERVIDOR:/root/
```
En el **servidor**:
```bash
cd /var/www && tar -xzf /root/eventos.tgz
nano /var/www/eventos/config.php
```
Deje `'api_url' => 'http://127.0.0.1:3000/api'` y en `'api_key'` el **mismo valor** de `API_KEY` del `.env` de la API.
```bash
chown -R www-data:www-data /var/www/eventos
```

## 7. Nginx y HTTPS
```bash
cp /ruta/a/nginx-eventos.conf /etc/nginx/sites-available/eventos   # reemplace TU_DOMINIO y la versión de php-fpm
ln -s /etc/nginx/sites-available/eventos /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx
certbot --nginx -d TU_DOMINIO -d www.TU_DOMINIO     # HTTPS gratis, se renueva solo
```
(El archivo `nginx-eventos.conf` está en esta carpeta `deploy`; cópielo al servidor con `scp`.)

## 8. Probar
- `https://TU_DOMINIO` abre el login; entre con el usuario admin.
- Cree un evento de tipo Lanzamiento (Administración > Eventos), elíjalo con "Control de Lanzamientos" y registre un dato de prueba.
- Suba una imagen de boleta y descárguela.

## 9. Respaldos (importante)
```bash
nano /root/.my.cnf        # [client]  user=root  password=SU_CLAVE   (chmod 600)
mkdir -p /root/respaldos
crontab -e                # agregue la línea:
0 2 * * * mysqldump api_boletas | gzip > /root/respaldos/api_$(date +\%F).sql.gz && find /root/respaldos -mtime +14 -delete
```
Además, en Kamatera puede programar *snapshots* del servidor desde su consola.

## Actualizar después
Repita el `tar` + `scp` de la parte que cambió (sin `config.php` ni `.env`), extraiga encima y, si cambió la API, `pm2 restart api-eventos`.

## Seguridad: resumen
- API y MySQL accesibles solo desde el propio servidor (firewall + `127.0.0.1`).
- Secretos (`API_KEY`, `JWT_SECRET`, clave de MySQL) nuevos, largos y distintos a los de su PC.
- Sin `info.php` ni carpeta `deploy` en el servidor.
- Cambie la contraseña del admin tras el primer ingreso.
