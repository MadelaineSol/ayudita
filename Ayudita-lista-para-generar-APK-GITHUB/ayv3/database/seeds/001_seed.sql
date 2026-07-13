-- =====================================================================
-- Ayudita - Seeds iniciales
-- Usuarios demo: admin@ayudita.app / Admin1234!  ·  demo / Demo1234!
-- ¡CAMBIAR LAS CONTRASEÑAS EN PRODUCCIÓN! (o usar backend/bin/create-admin.php)
-- =====================================================================
USE ayudita;

INSERT INTO settings (setting_key, setting_value, description) VALUES
  ('app_name', 'Ayudita', 'Nombre de la aplicación'),
  ('commission_percent', '10', 'Porcentaje que retiene la plataforma en cada pago'),
  ('tax_percent', '0', 'Impuestos aplicados sobre el pago'),
  ('currency', 'ARS', 'Moneda de la plataforma'),
  ('min_withdrawal', '1000', 'Monto mínimo de retiro para prestadores'),
  ('support_email', 'soporte@ayudita.app', 'Email de soporte');

INSERT INTO categories (name, slug, icon, description, sort_order) VALUES
  ('Paseador de perros', 'paseador-de-perros', '🐕', 'Paseos y cuidado de mascotas', 1),
  ('Niñera', 'ninera', '🧸', 'Cuidado de niños y niñas', 2),
  ('Secretaria', 'secretaria', '📋', 'Asistencia administrativa', 3),
  ('Electricista', 'electricista', '💡', 'Instalaciones y reparaciones eléctricas', 4),
  ('Plomero', 'plomero', '🔧', 'Plomería y sanitarios', 5),
  ('Jardinero', 'jardinero', '🌿', 'Jardinería y mantenimiento de espacios verdes', 6),
  ('Pintor', 'pintor', '🎨', 'Pintura de interiores y exteriores', 7),
  ('Albañil', 'albanil', '🧱', 'Construcción y refacciones', 8),
  ('Profesor particular', 'profesor-particular', '📚', 'Clases y apoyo escolar', 9),
  ('Cuidador de adultos', 'cuidador-de-adultos', '🤝', 'Acompañamiento de adultos mayores', 10),
  ('Limpieza', 'limpieza', '🧹', 'Limpieza de hogares y oficinas', 11),
  ('Técnico PC', 'tecnico-pc', '💻', 'Reparación de computadoras', 12),
  ('Gasista', 'gasista', '🔥', 'Instalaciones de gas matriculadas', 13),
  ('Cerrajero', 'cerrajero', '🔑', 'Aperturas y cambio de cerraduras', 14);

-- Administrador (email verificado)
INSERT INTO users (role, name, email, password_hash, status, email_verified_at) VALUES
  ('admin', 'Administración Ayudita', 'admin@ayudita.app',
   '$argon2id$v=19$m=65536,t=4,p=1$N052YU4vbkpLek9NaGJUdg$d6GS64nlR8VDo+grAzg6NnGF3AS67NlcTbZd/IAMJLo',
   'active', NOW());

-- Usuarios demo (cliente y prestadores)
INSERT INTO users (role, name, email, phone, password_hash, status, email_verified_at, city, lat, lng) VALUES
  ('client', 'Clara Cliente', 'clara@demo.app', '+54 11 5555-0001',
   '$argon2id$v=19$m=65536,t=4,p=1$c1hXNDVkQmNLUjIxSjNPZw$2ee4J2g3GUAEQZuQoAUwgfbeXe3tFO3PnPEZBc1KdJE',
   'active', NOW(), 'Buenos Aires', -34.6037000, -58.3816000),
  ('provider', 'Pablo Paseador', 'pablo@demo.app', '+54 11 5555-0002',
   '$argon2id$v=19$m=65536,t=4,p=1$c1hXNDVkQmNLUjIxSjNPZw$2ee4J2g3GUAEQZuQoAUwgfbeXe3tFO3PnPEZBc1KdJE',
   'active', NOW(), 'Buenos Aires', -34.5997000, -58.3819000),
  ('provider', 'Elena Electricista', 'elena@demo.app', '+54 11 5555-0003',
   '$argon2id$v=19$m=65536,t=4,p=1$c1hXNDVkQmNLUjIxSjNPZw$2ee4J2g3GUAEQZuQoAUwgfbeXe3tFO3PnPEZBc1KdJE',
   'active', NOW(), 'Buenos Aires', -34.6090000, -58.3838000);

INSERT INTO provider_profiles (user_id, bio, experience_years, rate_hour, rate_day, verified) VALUES
  ((SELECT id FROM users WHERE email = 'pablo@demo.app'),
   'Amo a los perros 🐶. Hace 5 años que paseo y cuido mascotas de todos los tamaños.',
   5, 3500.00, 20000.00, 1),
  ((SELECT id FROM users WHERE email = 'elena@demo.app'),
   'Electricista matriculada. Instalaciones seguras, prolijas y con garantía. ⚡',
   12, 9000.00, 60000.00, 1);

INSERT INTO provider_categories (provider_id, category_id) VALUES
  ((SELECT pp.id FROM provider_profiles pp JOIN users u ON u.id = pp.user_id WHERE u.email = 'pablo@demo.app'),
   (SELECT id FROM categories WHERE slug = 'paseador-de-perros')),
  ((SELECT pp.id FROM provider_profiles pp JOIN users u ON u.id = pp.user_id WHERE u.email = 'elena@demo.app'),
   (SELECT id FROM categories WHERE slug = 'electricista'));

INSERT INTO banners (title, emoji, link, sort_order) VALUES
  ('¡Bienvenido a Ayudita! 💛', '👋', NULL, 1),
  ('Prestadores verificados cerca tuyo', '✅', NULL, 2);
