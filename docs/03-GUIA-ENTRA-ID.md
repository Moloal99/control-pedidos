# Guía: registrar la aplicación en Entra ID (Microsoft 365)

Una sola aplicación registrada en Entra ID le da al sistema tres cosas:

| Para qué | Tipo de permiso | Permiso |
|---|---|---|
| Inicio de sesión con la cuenta @plasticoscarmen.com | Delegado | `openid`, `profile`, `email`, `User.Read` |
| Leer y escribir las 11 listas de SharePoint | Aplicación | `Sites.Selected` (recomendado) o `Sites.ReadWrite.All` |
| Enviar los avisos como reportesbi@ | Aplicación | `Mail.Send` (limitado a ese buzón) |

Hace falta una cuenta con rol **Administrador de aplicaciones** o **Administrador global** del inquilino.
El inquilino de Plásticos Carmen es `27250769-1586-414b-8402-5536efdbfa8a` (es el que usa la Power App).

---

## 1. Registrar la aplicación

1. Entre a <https://entra.microsoft.com> › **Aplicaciones** › **Registros de aplicaciones** › **Nuevo registro**.
2. Nombre: `Control de Pedidos`.
3. Tipos de cuenta: **Solo las cuentas de este directorio organizativo**.
4. URI de redirección: plataforma **Web**, valor `https://pedidos.plasticoscarmen.com/auth/microsoft/callback`
   (agregue también `http://localhost:8020/auth/microsoft/callback` para pruebas en el equipo de desarrollo).
5. **Registrar**. Copie el **Id. de aplicación (cliente)**: va en `MS_CLIENT_ID`.

## 2. Credencial: certificado (recomendado)

El certificado sirve para Graph **y** para la API de SharePoint, que es la única que permite subir y leer los
comprobantes adjuntos de las listas. Con un secreto el sistema funciona, pero sin adjuntos en SharePoint.

En PowerShell, en el servidor:

```powershell
$cert = New-SelfSignedCertificate -Subject "CN=ControlPedidos" -CertStoreLocation "Cert:\CurrentUser\My" -KeyExportPolicy Exportable -KeySpec Signature -KeyLength 2048 -NotAfter (Get-Date).AddYears(2)
Export-Certificate -Cert $cert -FilePath C:\temp\control-pedidos.cer
$clave = ConvertTo-SecureString -String "una-clave-temporal" -Force -AsPlainText
Export-PfxCertificate -Cert $cert -FilePath C:\temp\control-pedidos.pfx -Password $clave
$cert.Thumbprint
```

Convierta el `.pfx` a PEM sin contraseña (con el `openssl` que trae Git para Windows):

```powershell
& "C:\Program Files\Git\usr\bin\openssl.exe" pkcs12 -in C:\temp\control-pedidos.pfx -out C:\inetpub\control-pedidos\storage\app\private\entra\control-pedidos.pem -nodes -nocerts
```

- En Entra ID › la aplicación › **Certificados y secretos** › **Certificados** › **Cargar certificado** › suba el `.cer`.
- En el `.env`: `MS_CERT_PATH=storage/app/private/entra/control-pedidos.pem` y `MS_CERT_THUMBPRINT=` la huella que mostró `$cert.Thumbprint`.
- Borre `C:\temp\control-pedidos.pfx` cuando termine. El `.pem` no debe salir del servidor ni subirse a Git.

> Alternativa sin certificado: **Certificados y secretos** › **Nuevo secreto de cliente** y póngalo en `MS_CLIENT_SECRET`.
> Anote la fecha de vencimiento: cuando el secreto vence, el sistema deja de sincronizar.

## 3. Permisos

En **Permisos de API** › **Agregar un permiso** › **Microsoft Graph**:

- **Permisos delegados**: `openid`, `profile`, `email`, `User.Read`.
- **Permisos de aplicación**: `Sites.Selected` y `Mail.Send`.

Luego **Conceder consentimiento de administrador**.

### 3.1 Dar acceso solo al sitio de las listas (`Sites.Selected`)

`Sites.Selected` no abre ningún sitio por sí solo: hay que autorizar el sitio donde están las listas
`dim_*` / `fact_*` (el de la cuenta reportesbi@). Con el módulo de PnP PowerShell:

```powershell
Install-Module PnP.PowerShell -Scope CurrentUser
Connect-PnPOnline -Url "https://plasticoscarmenbo.sharepoint.com/sites/NOMBRE-DEL-SITIO" -Interactive -ClientId <id de una app de administración de PnP>
Grant-PnPAzureADAppSitePermission -AppId "<MS_CLIENT_ID>" -DisplayName "Control de Pedidos" -Site "https://plasticoscarmenbo.sharepoint.com/sites/NOMBRE-DEL-SITIO" -Permissions Write
```

Si prefiere no usar PnP, puede pedir `Sites.ReadWrite.All` (más amplio: da acceso a todos los sitios).

### 3.2 Limitar `Mail.Send` al buzón reportesbi@

Sin esta restricción la aplicación podría enviar como cualquier buzón. En Exchange Online PowerShell:

```powershell
Connect-ExchangeOnline
New-DistributionGroup -Name "Apps Control de Pedidos" -Type Security -PrimarySmtpAddress apps-pedidos@plasticoscarmen.com -Members reportesbi@plasticoscarmen.com
New-ApplicationAccessPolicy -AppId "<MS_CLIENT_ID>" -PolicyScopeGroupId apps-pedidos@plasticoscarmen.com -AccessRight RestrictAccess -Description "Control de Pedidos solo envía como reportesbi@"
Test-ApplicationAccessPolicy -Identity reportesbi@plasticoscarmen.com -AppId "<MS_CLIENT_ID>"
```

## 4. Completar el `.env` y activar

```ini
PEDIDOS_MODO=sharepoint
MS_TENANT_ID=27250769-1586-414b-8402-5536efdbfa8a
MS_CLIENT_ID=...
MS_CERT_PATH=storage/app/private/entra/control-pedidos.pem
MS_CERT_THUMBPRINT=...
SP_HOST=plasticoscarmenbo.sharepoint.com
SP_SITIO=/sites/NOMBRE-DEL-SITIO
MAIL_MAILER=graph
QUEUE_CONNECTION=database
```

Después:

```powershell
php artisan config:clear
php artisan sharepoint:sincronizar --completa
```

La primera pasada trae vendedores (usuarios y roles), clientes, productos y todo el historial.
En **Administración › Microsoft 365** se ve el estado de cada lista y se puede enviar un correo de prueba.

## 5. Comprobaciones

| Qué | Cómo |
|---|---|
| El inicio de sesión funciona | Entre con una cuenta registrada en `dim_VENDEDORES`. Una cuenta que no está ahí recibe un mensaje claro y queda en la bitácora como «Acceso rechazado». |
| Las listas se leen | `php artisan sharepoint:sincronizar` muestra filas por lista y ninguna con error. |
| Las columnas coinciden | Si una columna se renombró en SharePoint, el registro (`storage/logs`) avisa «la columna X no existe en la lista Y». Los nombres se toman tal como los ve la Power App. |
| Los correos salen | Administración › Microsoft 365 › «Enviar correo de prueba». |
| Los adjuntos suben | Cree una nota de venta de prueba y verifique el comprobante en «Datos adjuntos» del elemento en SharePoint. |
