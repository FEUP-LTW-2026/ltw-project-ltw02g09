PRAGMA foreign_keys = ON;
PRAGMA journal_mode = WAL;

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'member' CHECK(role IN ('member', 'trainer', 'admin')),
    name TEXT NOT NULL,
    profile_photo TEXT,
    active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS trainer_profiles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL UNIQUE,
    bio TEXT,
    specializations TEXT,
    certifications TEXT,
    experience_years INTEGER DEFAULT 0,
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS fitness_classes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    type TEXT NOT NULL,
    trainer_id INTEGER,
    capacity INTEGER NOT NULL DEFAULT 20,
    duration_minutes INTEGER NOT NULL DEFAULT 60,
    location TEXT NOT NULL DEFAULT 'Sala Principal',
    active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (trainer_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS class_schedule (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    class_id INTEGER NOT NULL,
    day_of_week INTEGER CHECK(day_of_week BETWEEN 0 AND 6),
    start_time TEXT NOT NULL,
    recurring INTEGER NOT NULL DEFAULT 1,
    specific_date TEXT,
    FOREIGN KEY (class_id) REFERENCES fitness_classes(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS class_enrollments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    class_id INTEGER NOT NULL,
    schedule_id INTEGER NOT NULL,
    member_id INTEGER NOT NULL,
    enrolled_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(schedule_id, member_id),
    FOREIGN KEY (class_id) REFERENCES fitness_classes(id) ON DELETE CASCADE,
    FOREIGN KEY (schedule_id) REFERENCES class_schedule(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS equipment (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    category TEXT NOT NULL,
    total_quantity INTEGER NOT NULL DEFAULT 1,
    available_quantity INTEGER NOT NULL DEFAULT 1,
    status TEXT NOT NULL DEFAULT 'disponivel' CHECK(status IN ('disponivel', 'em_uso', 'manutencao', 'inativo')),
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS reviews (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    class_id INTEGER NOT NULL,
    member_id INTEGER NOT NULL,
    rating INTEGER NOT NULL CHECK(rating BETWEEN 1 AND 5),
    comment TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(class_id, member_id),
    FOREIGN KEY (class_id) REFERENCES fitness_classes(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS pt_availability (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    trainer_id INTEGER NOT NULL,
    date TEXT NOT NULL,
    start_time TEXT NOT NULL,
    end_time TEXT NOT NULL,
    booked INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (trainer_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS pt_bookings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    trainer_id INTEGER NOT NULL,
    member_id INTEGER NOT NULL,
    availability_id INTEGER NOT NULL,
    date TEXT NOT NULL,
    start_time TEXT NOT NULL,
    end_time TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pendente' CHECK(status IN ('pendente', 'confirmado', 'cancelado', 'concluido')),
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (trainer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (availability_id) REFERENCES pt_availability(id) ON DELETE CASCADE
);

INSERT INTO users (username, email, password_hash, role, name, active) VALUES
('admin', 'admin@gymfit.pt', '$2y$12$i6R0Y1lOQ1HA1ZQBgJ/qduEQXRHR6MkSYUalEfv6dmw5C7VUOfIIG', 'admin', 'Administrador', 1),
('joao.silva', 'joao.silva@gymfit.pt', '$2y$12$GfGrNyKKw/0iee4BBtpfk.a0VuqPXRAQjrBRk4yxjKnqETqcXbetq', 'trainer', 'João Silva', 1),
('ana.santos', 'ana.santos@gymfit.pt', '$2y$12$GfGrNyKKw/0iee4BBtpfk.a0VuqPXRAQjrBRk4yxjKnqETqcXbetq', 'trainer', 'Ana Santos', 1),
('miguel.costa', 'miguel.costa@gymfit.pt', '$2y$12$GfGrNyKKw/0iee4BBtpfk.a0VuqPXRAQjrBRk4yxjKnqETqcXbetq', 'trainer', 'Miguel Costa', 1),
('member', 'member@gymfit.pt', '$2y$12$GfGrNyKKw/0iee4BBtpfk.a0VuqPXRAQjrBRk4yxjKnqETqcXbetq', 'member', 'Membro Teste', 1),
('maria.oliveira', 'maria.oliveira@gymfit.pt', '$2y$12$GfGrNyKKw/0iee4BBtpfk.a0VuqPXRAQjrBRk4yxjKnqETqcXbetq', 'member', 'Maria Oliveira', 1),
('pedro.fernandes', 'pedro.fernandes@gymfit.pt', '$2y$12$GfGrNyKKw/0iee4BBtpfk.a0VuqPXRAQjrBRk4yxjKnqETqcXbetq', 'member', 'Pedro Fernandes', 1);

INSERT INTO trainer_profiles (user_id, bio, specializations, certifications, experience_years) VALUES
(2, 'Treinador certificado com mais de 8 anos de experiência em musculação e condicionamento físico. Apaixonado por ajudar os seus clientes a atingirem os seus objetivos de fitness.', 'Musculação, CrossFit, Condicionamento Físico', 'CREF, CrossFit Level 2, Personal Trainer Certificado', 8),
(3, 'Especialista em Yoga e Pilates, com formação internacional e certificação em múltiplas vertentes desta disciplina. Acredita no equilíbrio entre corpo e mente.', 'Yoga, Pilates, Meditação, Alongamento', 'Yoga Alliance RYT-500, Pilates Mat & Equipment, Mindfulness Coach', 6),
(4, 'Especialista em cardio e HIIT, com paixão por desportos de alta intensidade. Certificado em primeiros socorros e nutrição desportiva.', 'HIIT, Spinning, Cardio, Nutrição Desportiva', 'ACSM Certified Exercise Physiologist, Spinning Instructor, Nutrição Desportiva', 5);

INSERT INTO fitness_classes (name, description, type, trainer_id, capacity, duration_minutes, location) VALUES
('Yoga Matinal', 'Aula de yoga para começar o dia com energia e tranquilidade. Indicada para todos os níveis.', 'Yoga', 3, 15, 60, 'Sala de Yoga'),
('HIIT Intenso', 'Treino de alta intensidade para queimar calorias e ganhar resistência. Para nível intermédio/avançado.', 'HIIT', 4, 20, 45, 'Sala Principal'),
('Pilates Core', 'Aula de pilates focada no fortalecimento do core e melhoria da postura.', 'Pilates', 3, 12, 55, 'Sala de Yoga'),
('Spinning Power', 'Aula de spinning de alta energia com música motivadora. Para todos os níveis.', 'Spinning', 4, 25, 50, 'Sala de Spinning'),
('Musculação Iniciante', 'Introdução ao treino de força com técnicas corretas e seguras.', 'Musculação', 2, 10, 60, 'Sala de Pesos'),
('CrossFit WOD', 'Workout of the Day com exercícios funcionais variados e intensos.', 'CrossFit', 2, 15, 60, 'Zona CrossFit'),
('Yoga Relaxamento', 'Sessão de yoga focada no relaxamento e recuperação muscular.', 'Yoga', 3, 18, 75, 'Sala de Yoga'),
('Cardio Dance', 'Aula de dança cardio divertida e energizante para queimar calorias.', 'Cardio', 4, 22, 45, 'Sala Principal');

INSERT INTO class_schedule (class_id, day_of_week, start_time, recurring) VALUES
(1, 1, '07:00', 1),
(1, 3, '07:00', 1),
(1, 5, '07:00', 1),
(2, 2, '18:00', 1),
(2, 4, '18:00', 1),
(2, 6, '10:00', 1),
(3, 1, '10:00', 1),
(3, 3, '10:00', 1),
(3, 5, '10:00', 1),
(4, 2, '19:00', 1),
(4, 4, '19:00', 1),
(4, 6, '11:00', 1),
(5, 1, '09:00', 1),
(5, 3, '09:00', 1),
(6, 2, '07:00', 1),
(6, 4, '07:00', 1),
(7, 5, '19:00', 1),
(7, 0, '10:00', 1),
(8, 2, '20:00', 1),
(8, 5, '20:00', 1);

INSERT INTO class_schedule (class_id, day_of_week, start_time, recurring, specific_date) VALUES
(1, CAST(strftime('%w','2026-05-30') AS INTEGER), '09:00', 0, '2026-05-30'),
(2, CAST(strftime('%w','2026-05-31') AS INTEGER), '11:00', 0, '2026-05-31'),
(3, CAST(strftime('%w','2026-06-02') AS INTEGER), '08:30', 0, '2026-06-02'),
(4, CAST(strftime('%w','2026-06-04') AS INTEGER), '18:30', 0, '2026-06-04'),
(5, CAST(strftime('%w','2026-06-06') AS INTEGER), '10:30', 0, '2026-06-06'),
(6, CAST(strftime('%w','2026-06-08') AS INTEGER), '07:30', 0, '2026-06-08'),
(7, CAST(strftime('%w','2026-06-10') AS INTEGER), '19:30', 0, '2026-06-10'),
(8, CAST(strftime('%w','2026-06-12') AS INTEGER), '20:30', 0, '2026-06-12');

INSERT INTO equipment (name, description, category, total_quantity, available_quantity, status) VALUES
('Passadeira', 'Passadeira elétrica com inclinação ajustável e monitor cardíaco', 'Cardio', 8, 5, 'disponivel'),
('Bicicleta Estática', 'Bicicleta estática com resistência magnética', 'Cardio', 10, 7, 'disponivel'),
('Elíptica', 'Máquina elíptica de baixo impacto', 'Cardio', 6, 4, 'disponivel'),
('Banco de Pesos', 'Banco ajustável para exercícios com halteres', 'Força', 12, 10, 'disponivel'),
('Barra Olímpica', 'Barra olímpica de 20kg com suporte', 'Força', 8, 6, 'disponivel'),
('Halteres 5kg', 'Par de halteres de 5kg', 'Força', 20, 16, 'disponivel'),
('Halteres 10kg', 'Par de halteres de 10kg', 'Força', 16, 12, 'disponivel'),
('Halteres 15kg', 'Par de halteres de 15kg', 'Força', 12, 9, 'disponivel'),
('Halteres 20kg', 'Par de halteres de 20kg', 'Força', 10, 8, 'disponivel'),
('Máquina de Remo', 'Máquina de remo para treino cardiovascular', 'Cardio', 4, 2, 'disponivel'),
('TRX', 'Sistema de suspensão para treino funcional', 'Funcional', 6, 5, 'disponivel'),
('Kettlebell 16kg', 'Kettlebell de 16kg para treino funcional', 'Funcional', 8, 6, 'disponivel'),
('Bola de Pilates', 'Bola de estabilidade para pilates e core', 'Aulas', 20, 18, 'disponivel'),
('Tapete de Yoga', 'Tapete antiderrapante para yoga e pilates', 'Aulas', 30, 25, 'disponivel'),
('Corda de Saltar', 'Corda de saltar profissional', 'Cardio', 15, 12, 'disponivel'),
('Plataforma Vibratória', 'Plataforma vibratória para recuperação muscular', 'Recuperação', 2, 1, 'disponivel'),
('Rolo de Espuma', 'Foam roller para massagem muscular', 'Recuperação', 12, 10, 'disponivel'),
('Bicicleta Spinning', 'Bicicleta de spinning profissional', 'Spinning', 20, 18, 'disponivel');

INSERT INTO pt_availability (trainer_id, date, start_time, end_time, booked) VALUES
(2, date('now', '+1 day'), '09:00', '10:00', 0),
(2, date('now', '+1 day'), '10:00', '11:00', 0),
(2, date('now', '+1 day'), '11:00', '12:00', 0),
(2, date('now', '+2 days'), '14:00', '15:00', 0),
(2, date('now', '+2 days'), '15:00', '16:00', 0),
(2, date('now', '+3 days'), '09:00', '10:00', 0),
(2, date('now', '+3 days'), '10:00', '11:00', 0),
(3, date('now', '+1 day'), '08:00', '09:00', 0),
(3, date('now', '+1 day'), '09:00', '10:00', 0),
(3, date('now', '+2 days'), '16:00', '17:00', 0),
(3, date('now', '+2 days'), '17:00', '18:00', 0),
(3, date('now', '+4 days'), '08:00', '09:00', 0),
(4, date('now', '+1 day'), '13:00', '14:00', 0),
(4, date('now', '+1 day'), '14:00', '15:00', 0),
(4, date('now', '+3 days'), '13:00', '14:00', 0),
(4, date('now', '+3 days'), '14:00', '15:00', 0),
(4, date('now', '+5 days'), '09:00', '10:00', 0);

INSERT INTO pt_availability (trainer_id, date, start_time, end_time, booked) VALUES
(2, '2026-06-01', '09:00', '10:00', 0),
(2, '2026-06-01', '10:00', '11:00', 0),
(2, '2026-06-03', '14:00', '15:00', 0),
(2, '2026-06-05', '08:30', '09:30', 0),
(3, '2026-06-02', '16:00', '17:00', 0),
(3, '2026-06-04', '18:00', '19:00', 0),
(3, '2026-06-06', '09:00', '10:00', 0),
(4, '2026-06-01', '13:00', '14:00', 0),
(4, '2026-06-03', '15:00', '16:00', 0),
(4, '2026-06-05', '10:00', '11:00', 0),
(4, '2026-06-07', '11:00', '12:00', 0),
(4, '2026-06-09', '09:30', '10:30', 0);

INSERT INTO pt_availability (trainer_id, date, start_time, end_time, booked) VALUES
(2, date('now', '+6 days'), '09:00', '10:00', 0),
(2, date('now', '+6 days'), '10:00', '11:00', 0),
(2, date('now', '+7 days'), '14:00', '15:00', 0),
(2, date('now', '+8 days'), '09:00', '10:00', 0),
(2, date('now', '+8 days'), '11:00', '12:00', 0),
(3, date('now', '+6 days'), '08:00', '09:00', 0),
(3, date('now', '+7 days'), '17:00', '18:00', 0),
(3, date('now', '+7 days'), '18:00', '19:00', 0),
(3, date('now', '+8 days'), '08:00', '09:00', 0),
(3, date('now', '+8 days'), '09:00', '10:00', 0),
(4, date('now', '+6 days'), '13:00', '14:00', 0),
(4, date('now', '+7 days'), '10:00', '11:00', 0),
(4, date('now', '+7 days'), '11:00', '12:00', 0),
(4, date('now', '+8 days'), '13:00', '14:00', 0),
(4, date('now', '+8 days'), '15:00', '16:00', 0);

INSERT INTO class_enrollments (class_id, schedule_id, member_id) VALUES
((SELECT id FROM fitness_classes WHERE name='Yoga Matinal'),
 (SELECT id FROM class_schedule WHERE class_id=(SELECT id FROM fitness_classes WHERE name='Yoga Matinal') ORDER BY id LIMIT 1),
 (SELECT id FROM users WHERE username='member')),
((SELECT id FROM fitness_classes WHERE name='HIIT Intenso'),
 (SELECT id FROM class_schedule WHERE class_id=(SELECT id FROM fitness_classes WHERE name='HIIT Intenso') ORDER BY id LIMIT 1),
 (SELECT id FROM users WHERE username='maria.oliveira'));

INSERT INTO reviews (class_id, member_id, rating, comment) VALUES
((SELECT id FROM fitness_classes WHERE name='Yoga Matinal'), (SELECT id FROM users WHERE username='member'), 5, 'Excelente aula, muito relaxante.'),
((SELECT id FROM fitness_classes WHERE name='HIIT Intenso'), (SELECT id FROM users WHERE username='maria.oliveira'), 4, 'Intenso mas muito bem orientado.');

INSERT INTO pt_bookings (trainer_id, member_id, availability_id, date, start_time, end_time, status, notes) VALUES
((SELECT id FROM users WHERE username='joao.silva'),
 (SELECT id FROM users WHERE username='member'),
 (SELECT id FROM pt_availability WHERE trainer_id=(SELECT id FROM users WHERE username='joao.silva') AND date >= date('now') ORDER BY date LIMIT 1),
 (SELECT date FROM pt_availability WHERE trainer_id=(SELECT id FROM users WHERE username='joao.silva') AND date >= date('now') ORDER BY date LIMIT 1),
 (SELECT start_time FROM pt_availability WHERE trainer_id=(SELECT id FROM users WHERE username='joao.silva') AND date >= date('now') ORDER BY date LIMIT 1),
 (SELECT end_time FROM pt_availability WHERE trainer_id=(SELECT id FROM users WHERE username='joao.silva') AND date >= date('now') ORDER BY date LIMIT 1),
 'pendente', 'Sessao demo');
