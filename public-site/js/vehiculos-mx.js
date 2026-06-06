// vehiculos-mx.js — Catálogo de vehículos mercado México
// Fuente base: EPS Servicio Automotriz (citaseps.com) — mercado MX real
// Complementado con clásicos en circulación que talleres siguen atendiendo

var VEHICULOS_MX = {

  'Acura': [
    'Integra', 'MDX', 'RDX', 'TLX'
  ],

  'Audi': [
    'A1 Sportback', 'A3 Sedán', 'A3 Sportback', 'A4', 'A5 Sportback',
    'Q2', 'Q3', 'Q5', 'Q7', 'Q8'
  ],

  'BMW': [
    'Serie 1', 'Serie 2', 'Serie 2 Gran Coupé', 'Serie 3', 'Serie 4', 'Serie 5',
    'X1', 'X2', 'X3', 'X4', 'X5', 'X6'
  ],

  'BYD': [
    'Dolphin', 'Seal', 'Shark', 'Song Plus', 'Song Pro', 'Atto 3'
  ],

  'Changan': [
    'Alsvin', 'Alsvin Plus', 'CS35 Plus', 'CS55 Plus', 'CS95',
    'Eado Plus', 'Hunter', 'UNI-K', 'UNI-T'
  ],

  'Chevrolet': [
    'Astro', 'Aveo', 'Beat', 'Blazer', 'Captiva', 'Cavalier',
    'Chevy', 'Cruze', 'Equinox', 'Express', 'Groove',
    'Malibu', 'Montana', 'Onix', 'Optra', 'S10 Max',
    'Silverado 1500', 'Silverado HD', 'Sonic', 'Spark', 'Suburban',
    'Tahoe', 'Tornado Van', 'Tracker', 'Traverse', 'Trax'
  ],

  'Chrysler': [
    '200', '300', '300C', 'Voyager'
  ],

  'Cupra': [
    'Ateca', 'Formentor', 'León'
  ],

  'Dodge': [
    'Attitude', 'Caravan', 'Challenger', 'Charger',
    'Durango', 'Journey', 'Neon', 'RAM 1500', 'RAM 2500', 'Verna', 'Vision'
  ],

  'Fiat': [
    'Argo', 'Ducato', 'Fastback', 'Mobi', 'Palio', 'Pulse', 'Siena', 'Toro', 'Uno'
  ],

  'Ford': [
    'Bronco', 'Bronco Sport', 'EcoSport', 'Edge', 'Escape',
    'Expedition', 'Explorer', 'Fiesta', 'Figo', 'Fusion',
    'Lobo', 'Maverick', 'Mustang', 'Mustang Mach-E',
    'Ranger', 'Territory', 'Transit', 'Windstar'
  ],

  'Honda': [
    'Accord', 'BR-V', 'City', 'Civic', 'CR-V',
    'Fit', 'HR-V', 'Odyssey', 'Pilot'
  ],

  'Hyundai': [
    'Accent', 'Atos', 'Creta', 'Elantra',
    'Grand i10 Hatchback', 'Grand i10 Sedán',
    'HB20', 'Palisade', 'Santa Fe', 'Sonata', 'Tucson', 'Venue'
  ],

  'Infiniti': [
    'Q50', 'Q60', 'QX50', 'QX55', 'QX60', 'QX80'
  ],

  'JAC': [
    'Frison T6', 'J7', 'Sei2 Pro', 'Sei4 Pro', 'Sei7 Pro', 'Sunray'
  ],

  'Jeep': [
    'Cherokee', 'Compass', 'Gladiator',
    'Grand Cherokee', 'Renegade', 'Wrangler'
  ],

  'Kia': [
    'Carnival', 'Forte', 'K3', 'K4', 'Niro',
    'Rio', 'Seltos', 'Sonet', 'Sorento',
    'Soul', 'Sportage', 'Telluride'
  ],

  'Land Rover': [
    'Defender', 'Discovery', 'Discovery Sport',
    'Range Rover', 'Range Rover Evoque',
    'Range Rover Sport', 'Range Rover Velar'
  ],

  'Lexus': [
    'ES', 'IS', 'LS', 'NX', 'RX', 'UX'
  ],

  'Mazda': [
    'BT-50', 'CX-3', 'CX-30', 'CX-5', 'CX-50',
    'CX-70', 'CX-9', 'CX-90', 'Mazda2', 'Mazda3 Hatchback',
    'Mazda3 Sedán', 'MX-5'
  ],

  'Mercedes-Benz': [
    'CLA', 'Clase A Sedán', 'Clase C', 'Clase E',
    'GLA', 'GLB', 'GLC', 'GLE', 'GLS', 'Sprinter'
  ],

  'MG': [
    'Extender', 'GT', 'HS', 'MG3', 'MG5', 'One', 'RX5', 'ZS'
  ],

  'Mini': [
    'Clubman', 'Convertible', 'Cooper', 'Cooper S',
    'Countryman', 'John Cooper Works', 'Paceman'
  ],

  'Mitsubishi': [
    'Galant', 'L200', 'Lancer', 'Mirage G4', 'Mirage Hatchback',
    'Montero', 'Montero Sport', 'Outlander', 'Outlander Sport',
    'Xpander', 'Xpander Cross'
  ],

  'Nissan': [
    'Altima', 'Armada', 'Frontier', 'GT-R',
    'Kicks', 'Magnite', 'March', 'Murano',
    'NP300', 'Note', 'Pathfinder', 'Platina',
    'Sentra', 'Tiida', 'Tsuru', 'Urvan',
    'V-Drive', 'Versa', 'X-Trail', 'Z'
  ],

  'Peugeot': [
    '2008', '208', '3008', '5008', 'Partner Rapid', 'Rifter'
  ],

  'Porsche': [
    '718 Boxster', '718 Cayman', '911', 'Cayenne',
    'Cayenne Coupe', 'Macan', 'Panamera', 'Taycan'
  ],

  'RAM': [
    '700', '1200', '1500', '2500', '3500',
    'ProMaster', 'ProMaster Rapid'
  ],

  'Renault': [
    'Captur', 'Duster', 'Kangoo', 'Koleos', 'Kwid',
    'Logan', 'Megane', 'Oroch', 'Sandero', 'Stepway'
  ],

  'SEAT': [
    'Arona', 'Ateca', 'Córdoba', 'Ibiza', 'León', 'Toledo'
  ],

  'Subaru': [
    'BRZ', 'Crosstrek', 'Forester', 'Outback', 'WRX'
  ],

  'Suzuki': [
    'Baleno', 'Ciaz', 'Dzire', 'Ertiga', 'Fronx',
    'Grand Vitara', 'Ignis', 'Jimny', 'S-Cross',
    'Swift', 'Vitara', 'XL7'
  ],

  'Toyota': [
    'Avanza', 'Camry', 'Corolla', 'Corolla Cross',
    'GR86', 'Highlander', 'Hilux', 'Land Cruiser',
    'Prius', 'RAV4', 'Raize', 'Sequoia', 'Sienna',
    'Supra', 'Tacoma', 'Tundra',
    'Yaris Hatchback', 'Yaris Sedán'
  ],

  'Volkswagen': [
    'Amarok', 'Beetle', 'Bora', 'Gol', 'Golf',
    'Jetta', 'Jetta Clásico', 'Lupo', 'Passat',
    'Pointer', 'Polo', 'Saveiro', 'T-Cross', 'Taigun',
    'Taos', 'Teramont', 'Teramont Cross Sport',
    'Tiguan', 'Vento', 'Virtus'
  ],

  'Volvo': [
    'S60', 'XC40', 'XC60', 'XC90'
  ]

};
