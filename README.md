# DMG Klantportaal

Een professioneel klantportaal gebouwd met PHP, MariaDB en Docker voor het beheren van klanten, producten, tickets en meer.

## Functionaliteiten

### Klantgedeelte ✅
- ✅ **Producten beheren**: Overzicht van hosting, domeinnamen, e-mailpakketten en SLA-contracten
- ✅ **Product details**: Registratiedatum, verloopdatum, gekoppelde domeinnamen
- ✅ **Producten opzeggen**: Opzegverzoek indienen met reden (admin goedkeuring vereist)
- ✅ **Producten aanvragen**: Nieuwe producten aanvragen met tracking
- ✅ **Ticketsysteem**: Tickets aanmaken, inzien en beantwoorden
- ✅ **Betaalvoorkeuren**: Factuur of automatisch incasso met mandaatbeheer (IBAN, datum, naam, handtekening)
- ✅ **Profielbeheer**: Persoonlijke gegevens en wachtwoord wijzigen

### Admingedeelte ✅
- ✅ **Gebruikersbeheer**: Gebruikers toevoegen en verwijderen
- ✅ **Productbeheer**: Producten toevoegen, koppelen, verlengen en opzeggen
- ✅ **Product aanvragen beheren**: Goedkeuren of afwijzen van klant aanvragen
- ✅ **Opzegverzoeken beheren**: Goedkeuren of afwijzen van opzeggingen
- ✅ **Ticketbeheer**: Alle tickets bekijken, status wijzigen en beantwoorden
- ✅ **Dashboard**: Overzicht van statistieken en verlopen producten

### Extra Features
- 📋 Verlopen producten monitoring (30 dagen vooruit)
- 📊 Uitgebreide statistieken en dashboards
- 🔐 Veilige wachtwoord hashing (bcrypt)
- 📝 Volledige audit trail (created_at, updated_at timestamps)
- 🎨 Responsive design voor alle schermformaten

## Technische Stack

- **Backend**: PHP 8.2
- **Database**: MariaDB 10.11
- **Webserver**: Apache
- **Containerisatie**: Docker & Docker Compose
- **Frontend**: HTML5, CSS3, JavaScript

## Installatie

### Vereisten
- Docker Desktop (Windows/Mac) of Docker Engine (Linux)
- Docker Compose

### Stappen

1. **Clone de repository**
```bash
git clone <repository-url>
cd dmg-klantenportaal
```

2. **Start de containers**
```bash
docker-compose up -d
```

3. **Wacht tot de containers zijn gestart**
De applicatie is beschikbaar op:
- Applicatie: http://localhost:8080
- phpMyAdmin: http://localhost:8081

4. **Login met demo accounts**

**Admin account:**
- Email: admin@dmg.nl
- Wachtwoord: admin123

**Klant account:**
- Email: demo@example.com
- Wachtwoord: customer123

## Projectstructuur

```
dmg-klantenportaal/
├── database/
│   └── init.sql                 # Database schema en initiële data
├── src/
│   ├── admin/                   # Admin interface
│   │   ├── dashboard.php
│   │   ├── users.php
│   │   ├── products.php
│   │   ├── tickets.php
│   │   └── includes/
│   │       ├── header.php
│   │       └── footer.php
│   ├── customer/                # Klant interface
│   │   ├── dashboard.php
│   │   ├── products.php
│   │   ├── tickets.php
│   │   ├── profile.php
│   │   └── includes/
│   │       ├── header.php
│   │       └── footer.php
│   ├── classes/                 # PHP klassen
│   │   ├── Auth.php
│   │   ├── User.php
│   │   ├── Product.php
│   │   └── Ticket.php
│   ├── config/                  # Configuratie
│   │   ├── config.php
│   │   └── Database.php
│   ├── css/                     # Styling
│   │   └── style.css
│   ├── index.php                # Login pagina
│   └── logout.php
├── docker-compose.yml
├── Dockerfile
└── README.md
```

## Database Schema

### Tabellen
- `users` - Gebruikers (klanten en admins)
- `products` - Producten (hosting, domeinen, etc.)
- `product_types` - Product categorieën
- `tickets` - Support tickets
- `ticket_messages` - Ticket berichten
- `chat_messages` - Chat berichten
- `payment_preferences` - Betaalvoorkeuren en mandaten
- `product_requests` - Nieuwe product aanvragen
- `cancellation_requests` - Opzeg verzoeken

## Development

### Logs bekijken
```bash
docker-compose logs -f
```

### Container herstarten
```bash
docker-compose restart
```

### Containers stoppen
```bash
docker-compose down
```

### Database opnieuw initialiseren
```bash
docker-compose down -v
docker-compose up -d
```

### Database backup maken
```bash
docker exec dmg_klantportaal_db mysqldump -u dmg_user -pdmg_password klantportaal > backup.sql
```

## Beveiliging

⚠️ **BELANGRIJK**: Wijzig de volgende zaken voor productie:
1. Standaard wachtwoorden in `docker-compose.yml`
2. Standaard admin wachtwoord in database
3. Zet `ENVIRONMENT` naar `production` in `docker-compose.yml`
4. Gebruik HTTPS in productie
5. Implementeer rate limiting voor login
6. Voeg CSRF-bescherming toe aan formulieren

## To-Do / Uitbreidingen

- [ ] Chat functionaliteit (real-time met WebSockets)
- [ ] E-mail notificaties voor belangrijke events
- [ ] Wachtwoord reset functionaliteit
- [ ] Twee-factor authenticatie
- [ ] API endpoints voor integraties
- [ ] Automatische facturering
- [ ] PDF facturen genereren
- [ ] Dashboard analytics en grafieken

## Support

Voor vragen of problemen, maak een ticket aan in het systeem of neem contact op met de beheerder.

## Licentie

Dit project is ontwikkeld voor educatieve doeleinden.
