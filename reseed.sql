-- =============================================================================
-- reseed.sql — Restore employees cleared on 2026-11-09
-- Source: docs/Biometric_logs/Daily-Log-H20260830_1439aug.xls
--
-- Branch mapping (from XLS Dept column):
--   LDE, HARDWARE          → branch_id=1 (BANGA-CON),  device_id=1 (DEVICE-BANGA)
--   AP BANGA               → branch_id=2 (BANGA-AUT),  device_id=1 (DEVICE-BANGA)
--   HOMEBUILD, BUILDDEX,
--   CENTER-POINT, TRIBAL   → branch_id=3 (SURALLAH),   device_id=2 (DEVICE-SURALLAH)
--   RESIGNED               → branch_id=1 (best-guess, status=Archived)
--
-- Enrollment code = Enroll ID from XLS (zero-padded to match parser behaviour).
-- All employees hired 2024-01-01 (placeholder; update per actual records).
-- Salary not included — enter through Manage Salary after import.
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 1. EMPLOYEES
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO employee
    (employee_number, employee_type, first_name, middle_initial, last_name,
     email, hire_date, position, status, created_at, updated_at)
VALUES
-- LDE dept (branch_id=1)
('EMP-100', 'Regular', 'Linda',         NULL, 'Lebuna',            NULL, '2024-01-01', 'Sales Clerk',     'Active',   NOW(), NOW()),
('EMP-126', 'Regular', 'Menen',         NULL, 'Dela Cruz',         NULL, '2024-01-01', 'Sales Clerk',     'Active',   NOW(), NOW()),
('EMP-129', 'Regular', 'Ronan',         NULL, 'Dela Cruz',         NULL, '2024-01-01', 'Sales Clerk',     'Active',   NOW(), NOW()),
('EMP-137', 'Regular', 'Irish',         NULL, 'Bansilao',          NULL, '2024-01-01', 'Sales Clerk',     'Active',   NOW(), NOW()),
('EMP-015', 'Regular', 'Roce',          NULL, 'Catayoc',           NULL, '2024-01-01', 'Sales Clerk',     'Active',   NOW(), NOW()),
('EMP-052', 'Regular', 'Lovely',        NULL, 'Paguntalan',        NULL, '2024-01-01', 'Sales Clerk',     'Active',   NOW(), NOW()),
('EMP-054', 'Regular', 'Melanie',       NULL, 'Vidal',             NULL, '2024-01-01', 'Sales Clerk',     'Active',   NOW(), NOW()),
('EMP-055', 'Regular', 'Laurence',      NULL, 'Amara',             NULL, '2024-01-01', 'Laborer',         'Active',   NOW(), NOW()),
('EMP-061', 'Regular', 'Joy',           NULL, 'Ga-an',             NULL, '2024-01-01', 'Sales Clerk',     'Active',   NOW(), NOW()),
('EMP-078', 'Regular', 'Anjo',          NULL, 'Ga-an',             NULL, '2024-01-01', 'Laborer',         'Active',   NOW(), NOW()),
('EMP-086', 'Regular', 'K',             NULL, 'Gulmatico',         NULL, '2024-01-01', 'Sales Clerk',     'Active',   NOW(), NOW()),
('EMP-092', 'Regular', 'Jenelyn',       NULL, 'Carpizo',           NULL, '2024-01-01', 'Sales Clerk',     'Active',   NOW(), NOW()),
-- HARDWARE dept (branch_id=1)
('EMP-001', 'Regular', 'Nyven',         NULL, 'Defino',            NULL, '2024-01-01', 'Sales Clerk',     'Active',   NOW(), NOW()),
('EMP-108', 'Regular', 'Barbie',        NULL, 'Catayoc',           NULL, '2024-01-01', 'Sales Clerk',     'Active',   NOW(), NOW()),
('EMP-114', 'Regular', 'Albert',        NULL, 'Indo',              NULL, '2024-01-01', 'Laborer',         'Active',   NOW(), NOW()),
('EMP-121', 'Regular', 'Joshua',        NULL, 'Pugay',             NULL, '2024-01-01', 'Laborer',         'Active',   NOW(), NOW()),
('EMP-123', 'Regular', 'John Lloyd',    NULL, 'Rusiana',           NULL, '2024-01-01', 'Laborer',         'Active',   NOW(), NOW()),
('EMP-124', 'Regular', 'John Dave',     NULL, 'Tagaduar',          NULL, '2024-01-01', 'Laborer',         'Active',   NOW(), NOW()),
('EMP-127', 'Regular', 'Manny',         NULL, 'Linsaw',            NULL, '2024-01-01', 'Laborer',         'Active',   NOW(), NOW()),
('EMP-013', 'Regular', 'Isabel',        NULL, 'Tayong',            NULL, '2024-01-01', 'Sales Clerk',     'Active',   NOW(), NOW()),
('EMP-132', 'Regular', 'Cyril',         NULL, 'Pugay',             NULL, '2024-01-01', 'Laborer',         'Active',   NOW(), NOW()),
('EMP-134', 'Regular', 'John Rey',      NULL, 'Canane',            NULL, '2024-01-01', 'Laborer',         'Active',   NOW(), NOW()),
('EMP-135', 'Regular', 'Leomer',        NULL, 'Reponte',           NULL, '2024-01-01', 'Laborer',         'Active',   NOW(), NOW()),
('EMP-136', 'Regular', 'Ryan',          NULL, 'Algan',             NULL, '2024-01-01', 'Laborer',         'Active',   NOW(), NOW()),
('EMP-138', 'Regular', 'Charlie',       NULL, 'Batacandolo',       NULL, '2024-01-01', 'Laborer',         'Active',   NOW(), NOW()),
('EMP-019', 'Regular', 'Kenneth',       NULL, 'Magdalga',          NULL, '2024-01-01', 'Laborer',         'Active',   NOW(), NOW()),
('EMP-002', 'Regular', 'Joa',           NULL, 'Villegas',          NULL, '2024-01-01', 'Sales Clerk',     'Active',   NOW(), NOW()),
('EMP-021', 'Regular', 'Arben',         NULL, 'Ramirez',           NULL, '2024-01-01', 'Laborer',         'Active',   NOW(), NOW()),
('EMP-023', 'Regular', 'Chito',         NULL, 'Tiad',              NULL, '2024-01-01', 'Laborer',         'Active',   NOW(), NOW()),
('EMP-024', 'Regular', 'Cris',          NULL, 'Tiad',              NULL, '2024-01-01', 'Laborer',         'Active',   NOW(), NOW()),
('EMP-032', 'Regular', 'Herwin',        NULL, 'Libuna',            NULL, '2024-01-01', 'Laborer',         'Active',   NOW(), NOW()),
('EMP-053', 'Regular', 'Jayvan',        NULL, 'Canete',            NULL, '2024-01-01', 'Laborer',         'Active',   NOW(), NOW()),
('EMP-006', 'Regular', 'Ian',           NULL, 'Pasignasigna',      NULL, '2024-01-01', 'Laborer',         'Active',   NOW(), NOW()),
('EMP-065', 'Regular', 'Dave',          NULL, 'Dalipe',            NULL, '2024-01-01', 'Laborer',         'Active',   NOW(), NOW()),
('EMP-008', 'Regular', 'Lowel',         NULL, 'Dalipe',            NULL, '2024-01-01', 'Laborer',         'Active',   NOW(), NOW()),
('EMP-084', 'Regular', 'Rojel',         NULL, 'Resma',             NULL, '2024-01-01', 'Laborer',         'Active',   NOW(), NOW()),
('EMP-094', 'Regular', 'Ritchie',       NULL, 'Tagaduar',          NULL, '2024-01-01', 'Laborer',         'Active',   NOW(), NOW()),
('EMP-095', 'Regular', 'Cheche',        NULL, 'Gonzales',          NULL, '2024-01-01', 'Sales Clerk',     'Active',   NOW(), NOW()),
-- AP BANGA dept (branch_id=2)
('EMP-026', 'Regular', 'Arian',         NULL, 'Catayoc',           NULL, '2024-01-01', 'Sales Clerk',     'Active',   NOW(), NOW()),
('EMP-027', 'Regular', 'Edwin',         NULL, 'Dapatnapo',         NULL, '2024-01-01', 'Laborer',         'Active',   NOW(), NOW()),
('EMP-043', 'Regular', 'Livy',          NULL, 'Padilla',           NULL, '2024-01-01', 'Sales Clerk',     'Active',   NOW(), NOW()),
('EMP-070', 'Regular', 'Jayson',        NULL, 'De Villa',          NULL, '2024-01-01', 'Laborer',         'Active',   NOW(), NOW()),
-- HOMEBUILD dept (branch_id=3)
('EMP-106', 'Regular', 'Laarnie',       NULL, 'Pedregosa',         NULL, '2024-01-01', 'Sales Clerk',     'Active',   NOW(), NOW()),
('EMP-125', 'Regular', 'Psalms Hymnary',NULL, 'Rollo',             NULL, '2024-01-01', 'Sales Clerk',     'Active',   NOW(), NOW()),
('EMP-020', 'Regular', 'Joey',          NULL, 'Bandalan',          NULL, '2024-01-01', 'Laborer',         'Active',   NOW(), NOW()),
('EMP-042', 'Regular', 'Charlo',        NULL, 'Arancillo',         NULL, '2024-01-01', 'Laborer',         'Active',   NOW(), NOW()),
('EMP-059', 'Regular', 'Rudy',          NULL, 'Eding',             NULL, '2024-01-01', 'Laborer',         'Active',   NOW(), NOW()),
('EMP-083', 'Regular', 'Regine',        NULL, 'Estorque',          NULL, '2024-01-01', 'Sales Clerk',     'Active',   NOW(), NOW()),
-- BUILDDEX dept (branch_id=3)
('EMP-103', 'Regular', 'Rubie',         NULL, 'Lumakad',           NULL, '2024-01-01', 'Sales Clerk',     'Active',   NOW(), NOW()),
('EMP-104', 'Regular', 'Ramil',         NULL, 'Lumakad',           NULL, '2024-01-01', 'Laborer',         'Active',   NOW(), NOW()),
('EMP-058', 'Regular', 'Leni',          NULL, 'Parrenas',          NULL, '2024-01-01', 'Sales Clerk',     'Active',   NOW(), NOW()),
('EMP-074', 'Regular', 'Rob',           NULL, 'Mahinay',           NULL, '2024-01-01', 'Laborer',         'Active',   NOW(), NOW()),
-- CENTER-POINT dept (branch_id=3)
('EMP-089', 'Regular', 'Saljean',       NULL, 'Caritativo',        NULL, '2024-01-01', 'Sales Clerk',     'Active',   NOW(), NOW()),
('EMP-097', 'Regular', 'Mark',          NULL, 'Belmonte',          NULL, '2024-01-01', 'Sales Clerk',     'Active',   NOW(), NOW()),
-- TRIBAL dept (branch_id=3)
('EMP-017', 'Regular', 'Mercy',         NULL, 'Geal',              NULL, '2024-01-01', 'Sales Clerk',     'Active',   NOW(), NOW()),
-- RESIGNED (Archived)
('EMP-010', 'Regular', 'Jam',           NULL, 'Lamputi',           NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-101', 'Regular', 'An',            NULL, 'Protacio',          NULL, '2024-01-01', 'Sales Clerk',     'Archived', NOW(), NOW()),
('EMP-102', 'Regular', 'Ji',            NULL, 'Millones',          NULL, '2024-01-01', 'Sales Clerk',     'Archived', NOW(), NOW()),
('EMP-105', 'Regular', 'Jen',           NULL, 'Sibayan',           NULL, '2024-01-01', 'Sales Clerk',     'Archived', NOW(), NOW()),
('EMP-107', 'Regular', 'Dulce',         NULL, 'Gayapa',            NULL, '2024-01-01', 'Sales Clerk',     'Archived', NOW(), NOW()),
('EMP-109', 'Regular', 'Aub',           NULL, 'Esteban',           NULL, '2024-01-01', 'Sales Clerk',     'Archived', NOW(), NOW()),
('EMP-011', 'Regular', 'Jovy',          NULL, 'Tayong',            NULL, '2024-01-01', 'Sales Clerk',     'Archived', NOW(), NOW()),
('EMP-110', 'Regular', 'Jess',          NULL, 'Sancho',            NULL, '2024-01-01', 'Sales Clerk',     'Archived', NOW(), NOW()),
('EMP-111', 'Regular', 'Jenny',         NULL, 'Sabidal',           NULL, '2024-01-01', 'Sales Clerk',     'Archived', NOW(), NOW()),
('EMP-112', 'Regular', 'Jeni',          NULL, 'Esposo',            NULL, '2024-01-01', 'Sales Clerk',     'Archived', NOW(), NOW()),
('EMP-113', 'Regular', 'La',            NULL, 'Espanola',          NULL, '2024-01-01', 'Sales Clerk',     'Archived', NOW(), NOW()),
('EMP-115', 'Regular', 'Wha',           NULL, 'Conejar',           NULL, '2024-01-01', 'Sales Clerk',     'Archived', NOW(), NOW()),
('EMP-116', 'Regular', 'Ju',            NULL, 'Pantaras',          NULL, '2024-01-01', 'Sales Clerk',     'Archived', NOW(), NOW()),
('EMP-117', 'Regular', 'Natnat',        NULL, 'Catayoc',           NULL, '2024-01-01', 'Sales Clerk',     'Archived', NOW(), NOW()),
('EMP-118', 'Regular', 'Jan Mark',      NULL, 'Gabuat',            NULL, '2024-01-01', 'Sales Clerk',     'Archived', NOW(), NOW()),
('EMP-119', 'Regular', 'Saym',          NULL, 'Tuazon',            NULL, '2024-01-01', 'Sales Clerk',     'Archived', NOW(), NOW()),
('EMP-012', 'Regular', 'Unknown',       NULL, 'Calasagsag',        NULL, '2024-01-01', 'Sales Clerk',     'Archived', NOW(), NOW()),
('EMP-120', 'Regular', 'Alvin',         NULL, 'Tucan',             NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-122', 'Regular', 'Rekrek',        NULL, 'Bernabe',           NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-128', 'Regular', 'Elvis',         NULL, 'Pastolero',         NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-130', 'Regular', 'Jessa Mae',     NULL, 'Patricio',          NULL, '2024-01-01', 'Sales Clerk',     'Archived', NOW(), NOW()),
('EMP-131', 'Regular', 'Edcil',         NULL, 'Rusiana',           NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-133', 'Regular', 'Jerson',        NULL, 'Bitantos',          NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-014', 'Regular', 'Maylen',        NULL, 'Belotindos',        NULL, '2024-01-01', 'Sales Clerk',     'Archived', NOW(), NOW()),
('EMP-016', 'Regular', 'Mae',           NULL, 'Lamputi',           NULL, '2024-01-01', 'Sales Clerk',     'Archived', NOW(), NOW()),
('EMP-018', 'Regular', 'Nov',           NULL, 'Dumadag',           NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-022', 'Regular', 'Romel',         NULL, 'Ginez',             NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-025', 'Regular', 'Jimmy',         NULL, 'Quilla',            NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-028', 'Regular', 'Nonoy',         NULL, 'Celiz',             NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-029', 'Regular', 'Char',          NULL, 'Estiva',            NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-003', 'Regular', 'Jaime',         NULL, 'Enrijo',            NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-030', 'Regular', 'Jeric',         NULL, 'Fanta',             NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-031', 'Regular', 'Jin',           NULL, 'Gamotin',           NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-033', 'Regular', 'Jurr',          NULL, 'Luneta',            NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-034', 'Regular', 'Ju',            NULL, 'Gonzales',          NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-035', 'Regular', 'Shiel',         NULL, 'Magan',             NULL, '2024-01-01', 'Sales Clerk',     'Archived', NOW(), NOW()),
('EMP-036', 'Regular', 'Jessi',         NULL, 'Monan',             NULL, '2024-01-01', 'Sales Clerk',     'Archived', NOW(), NOW()),
('EMP-037', 'Regular', 'Da',            NULL, 'Parrenas',          NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-038', 'Regular', 'Joseph',        NULL, 'Carbajal',          NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-039', 'Regular', 'Mela',          NULL, 'Carpizo',           NULL, '2024-01-01', 'Sales Clerk',     'Archived', NOW(), NOW()),
('EMP-004', 'Regular', 'Jerry',         NULL, 'Geal',              NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-040', 'Regular', 'Deo',           NULL, 'Ledesma',           NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-041', 'Regular', 'J',             NULL, 'Labaclado',         NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-044', 'Regular', 'Ro',            NULL, 'Tibianosa',         NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-045', 'Regular', 'Je',            NULL, 'Gayanoche',         NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-046', 'Regular', 'Soc',           NULL, 'Lariosa',           NULL, '2024-01-01', 'Sales Clerk',     'Archived', NOW(), NOW()),
('EMP-047', 'Regular', 'Unknown',       NULL, 'Millondaga',        NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-048', 'Regular', 'Nadea',         NULL, 'Goles',             NULL, '2024-01-01', 'Sales Clerk',     'Archived', NOW(), NOW()),
('EMP-049', 'Regular', 'Unknown',       NULL, 'Balladares',        NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-005', 'Regular', 'Ralph',         NULL, 'Bermejo',           NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-050', 'Regular', 'Dio',           NULL, 'Pamonag',           NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-051', 'Regular', 'Reg',           NULL, 'Atienzo',           NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-056', 'Regular', 'Mary',          NULL, 'Binas',             NULL, '2024-01-01', 'Sales Clerk',     'Archived', NOW(), NOW()),
('EMP-057', 'Regular', 'Jo',            NULL, 'Palencia',          NULL, '2024-01-01', 'Sales Clerk',     'Archived', NOW(), NOW()),
('EMP-060', 'Regular', 'Jean',          NULL, 'Mendez',            NULL, '2024-01-01', 'Sales Clerk',     'Archived', NOW(), NOW()),
('EMP-062', 'Regular', 'Jona',          NULL, 'Ferrer',            NULL, '2024-01-01', 'Sales Clerk',     'Archived', NOW(), NOW()),
('EMP-063', 'Regular', 'Jan',           NULL, 'Defino',            NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-064', 'Regular', 'Jess',          NULL, 'Defino',            NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-066', 'Regular', 'F',             NULL, 'Pastolero',         NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-067', 'Regular', 'Yb',            NULL, 'Bulbeque',          NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-068', 'Regular', 'Em',            NULL, 'Claveria',          NULL, '2024-01-01', 'Sales Clerk',     'Archived', NOW(), NOW()),
('EMP-069', 'Regular', 'Reynal',        NULL, 'Arca',              NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-007', 'Regular', 'Bern',          NULL, 'Fuentes',           NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-071', 'Regular', 'Mar',           NULL, 'Mahinay',           NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-072', 'Regular', 'Unknown',       NULL, 'Villanueva',        NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-073', 'Regular', 'Joel',          NULL, 'Galvan',            NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-075', 'Regular', 'Mar',           NULL, 'Doronio',           NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-076', 'Regular', 'Ariel',         NULL, 'Teodocio',          NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-077', 'Regular', 'Junw',          NULL, 'Lamera',            NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-079', 'Regular', 'Ben',           NULL, 'Fuentes',           NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-080', 'Regular', 'Manelyn',       NULL, 'Mand',              NULL, '2024-01-01', 'Sales Clerk',     'Archived', NOW(), NOW()),
('EMP-081', 'Regular', 'Tessie',        NULL, 'Budiasa',           NULL, '2024-01-01', 'Sales Clerk',     'Archived', NOW(), NOW()),
('EMP-082', 'Regular', 'Ni',            NULL, 'Guilaran',          NULL, '2024-01-01', 'Sales Clerk',     'Archived', NOW(), NOW()),
('EMP-085', 'Regular', 'Ja',            NULL, 'Servanez',          NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-087', 'Regular', 'A',             NULL, 'Parohinog',         NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-088', 'Regular', 'Jovel',         NULL, 'Esposo',            NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-009', 'Regular', 'E',             NULL, 'Pastolero',         NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-090', 'Regular', 'Jung',          NULL, 'Cuello',            NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-091', 'Regular', 'Ivan',          NULL, 'Binas',             NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-093', 'Regular', 'Jera',          NULL, 'Fronda',            NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-096', 'Regular', 'Mi',            NULL, 'Hontanar',          NULL, '2024-01-01', 'Laborer',         'Archived', NOW(), NOW()),
('EMP-098', 'Regular', 'Mel',           NULL, 'Sardido',           NULL, '2024-01-01', 'Sales Clerk',     'Archived', NOW(), NOW()),
('EMP-099', 'Regular', 'An',            NULL, 'Maghinay',          NULL, '2024-01-01', 'Sales Clerk',     'Archived', NOW(), NOW());

-- -----------------------------------------------------------------------------
-- 2. BRANCH ASSIGNMENTS
-- Dept → branch_id:
--   LDE, HARDWARE = 1 (BANGA-CON)
--   AP BANGA      = 2 (BANGA-AUT)
--   HOMEBUILD, BUILDDEX, CENTER-POINT, TRIBAL = 3 (SURALLAH)
--   RESIGNED      = 1 (default, Archived)
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO employee_branch_assignment
    (employee_id, branch_id, effective_from, effective_to, transfer_reason, transferred_by, created_at)
SELECT e.employee_id, b.branch_id, '2024-01-01', NULL, 'Initial assignment', 1, NOW()
FROM employee e
JOIN (
    SELECT 'EMP-100' AS emp_num, 1 AS branch_id UNION ALL
    SELECT 'EMP-126', 1 UNION ALL SELECT 'EMP-129', 1 UNION ALL SELECT 'EMP-137', 1 UNION ALL
    SELECT 'EMP-015', 1 UNION ALL SELECT 'EMP-052', 1 UNION ALL SELECT 'EMP-054', 1 UNION ALL
    SELECT 'EMP-055', 1 UNION ALL SELECT 'EMP-061', 1 UNION ALL SELECT 'EMP-078', 1 UNION ALL
    SELECT 'EMP-086', 1 UNION ALL SELECT 'EMP-092', 1 UNION ALL
    SELECT 'EMP-001', 1 UNION ALL SELECT 'EMP-108', 1 UNION ALL SELECT 'EMP-114', 1 UNION ALL
    SELECT 'EMP-121', 1 UNION ALL SELECT 'EMP-123', 1 UNION ALL SELECT 'EMP-124', 1 UNION ALL
    SELECT 'EMP-127', 1 UNION ALL SELECT 'EMP-013', 1 UNION ALL SELECT 'EMP-132', 1 UNION ALL
    SELECT 'EMP-134', 1 UNION ALL SELECT 'EMP-135', 1 UNION ALL SELECT 'EMP-136', 1 UNION ALL
    SELECT 'EMP-138', 1 UNION ALL SELECT 'EMP-019', 1 UNION ALL SELECT 'EMP-002', 1 UNION ALL
    SELECT 'EMP-021', 1 UNION ALL SELECT 'EMP-023', 1 UNION ALL SELECT 'EMP-024', 1 UNION ALL
    SELECT 'EMP-032', 1 UNION ALL SELECT 'EMP-053', 1 UNION ALL SELECT 'EMP-006', 1 UNION ALL
    SELECT 'EMP-065', 1 UNION ALL SELECT 'EMP-008', 1 UNION ALL SELECT 'EMP-084', 1 UNION ALL
    SELECT 'EMP-094', 1 UNION ALL SELECT 'EMP-095', 1 UNION ALL
    SELECT 'EMP-026', 2 UNION ALL SELECT 'EMP-027', 2 UNION ALL SELECT 'EMP-043', 2 UNION ALL
    SELECT 'EMP-070', 2 UNION ALL
    SELECT 'EMP-106', 3 UNION ALL SELECT 'EMP-125', 3 UNION ALL SELECT 'EMP-020', 3 UNION ALL
    SELECT 'EMP-042', 3 UNION ALL SELECT 'EMP-059', 3 UNION ALL SELECT 'EMP-083', 3 UNION ALL
    SELECT 'EMP-103', 3 UNION ALL SELECT 'EMP-104', 3 UNION ALL SELECT 'EMP-058', 3 UNION ALL
    SELECT 'EMP-074', 3 UNION ALL SELECT 'EMP-089', 3 UNION ALL SELECT 'EMP-097', 3 UNION ALL
    SELECT 'EMP-017', 3 UNION ALL
    -- RESIGNED → branch_id=1
    SELECT 'EMP-010', 1 UNION ALL SELECT 'EMP-101', 1 UNION ALL SELECT 'EMP-102', 1 UNION ALL
    SELECT 'EMP-105', 1 UNION ALL SELECT 'EMP-107', 1 UNION ALL SELECT 'EMP-109', 1 UNION ALL
    SELECT 'EMP-011', 1 UNION ALL SELECT 'EMP-110', 1 UNION ALL SELECT 'EMP-111', 1 UNION ALL
    SELECT 'EMP-112', 1 UNION ALL SELECT 'EMP-113', 1 UNION ALL SELECT 'EMP-115', 1 UNION ALL
    SELECT 'EMP-116', 1 UNION ALL SELECT 'EMP-117', 1 UNION ALL SELECT 'EMP-118', 1 UNION ALL
    SELECT 'EMP-119', 1 UNION ALL SELECT 'EMP-012', 1 UNION ALL SELECT 'EMP-120', 1 UNION ALL
    SELECT 'EMP-122', 1 UNION ALL SELECT 'EMP-128', 1 UNION ALL SELECT 'EMP-130', 1 UNION ALL
    SELECT 'EMP-131', 1 UNION ALL SELECT 'EMP-133', 1 UNION ALL SELECT 'EMP-014', 1 UNION ALL
    SELECT 'EMP-016', 1 UNION ALL SELECT 'EMP-018', 1 UNION ALL SELECT 'EMP-022', 1 UNION ALL
    SELECT 'EMP-025', 1 UNION ALL SELECT 'EMP-028', 1 UNION ALL SELECT 'EMP-029', 1 UNION ALL
    SELECT 'EMP-003', 1 UNION ALL SELECT 'EMP-030', 1 UNION ALL SELECT 'EMP-031', 1 UNION ALL
    SELECT 'EMP-033', 1 UNION ALL SELECT 'EMP-034', 1 UNION ALL SELECT 'EMP-035', 1 UNION ALL
    SELECT 'EMP-036', 1 UNION ALL SELECT 'EMP-037', 1 UNION ALL SELECT 'EMP-038', 1 UNION ALL
    SELECT 'EMP-039', 1 UNION ALL SELECT 'EMP-004', 1 UNION ALL SELECT 'EMP-040', 1 UNION ALL
    SELECT 'EMP-041', 1 UNION ALL SELECT 'EMP-044', 1 UNION ALL SELECT 'EMP-045', 1 UNION ALL
    SELECT 'EMP-046', 1 UNION ALL SELECT 'EMP-047', 1 UNION ALL SELECT 'EMP-048', 1 UNION ALL
    SELECT 'EMP-049', 1 UNION ALL SELECT 'EMP-005', 1 UNION ALL SELECT 'EMP-050', 1 UNION ALL
    SELECT 'EMP-051', 1 UNION ALL SELECT 'EMP-056', 1 UNION ALL SELECT 'EMP-057', 1 UNION ALL
    SELECT 'EMP-060', 1 UNION ALL SELECT 'EMP-062', 1 UNION ALL SELECT 'EMP-063', 1 UNION ALL
    SELECT 'EMP-064', 1 UNION ALL SELECT 'EMP-066', 1 UNION ALL SELECT 'EMP-067', 1 UNION ALL
    SELECT 'EMP-068', 1 UNION ALL SELECT 'EMP-069', 1 UNION ALL SELECT 'EMP-007', 1 UNION ALL
    SELECT 'EMP-071', 1 UNION ALL SELECT 'EMP-072', 1 UNION ALL SELECT 'EMP-073', 1 UNION ALL
    SELECT 'EMP-075', 1 UNION ALL SELECT 'EMP-076', 1 UNION ALL SELECT 'EMP-077', 1 UNION ALL
    SELECT 'EMP-079', 1 UNION ALL SELECT 'EMP-080', 1 UNION ALL SELECT 'EMP-081', 1 UNION ALL
    SELECT 'EMP-082', 1 UNION ALL SELECT 'EMP-085', 1 UNION ALL SELECT 'EMP-087', 1 UNION ALL
    SELECT 'EMP-088', 1 UNION ALL SELECT 'EMP-009', 1 UNION ALL SELECT 'EMP-090', 1 UNION ALL
    SELECT 'EMP-091', 1 UNION ALL SELECT 'EMP-093', 1 UNION ALL SELECT 'EMP-096', 1 UNION ALL
    SELECT 'EMP-098', 1 UNION ALL SELECT 'EMP-099', 1
) AS m ON m.emp_num = e.employee_number
JOIN branch b ON b.branch_id = m.branch_id;

-- -----------------------------------------------------------------------------
-- 3. BIOMETRIC ENROLLMENTS
-- Enrollment code = XLS Enroll ID (as stored — no zero-padding needed here
-- since the parser preserves the raw value from the XLS cell).
-- device_id=1 (DEVICE-BANGA) for branch 1 & 2; device_id=2 (DEVICE-SURALLAH) for branch 3.
-- RESIGNED employees enrolled on DEVICE-BANGA (device_id=1), status=Inactive.
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO employee_biometric_enrollment
    (employee_id, device_id, device_employee_code, effective_from, effective_to, status, created_at, updated_at)
SELECT e.employee_id, m.device_id, m.code, '2024-01-01', NULL, m.enroll_status, NOW(), NOW()
FROM employee e
JOIN (
    -- Active employees
    SELECT 'EMP-100' AS emp_num, 1 AS device_id, '100' AS code, 'Active' AS enroll_status UNION ALL
    SELECT 'EMP-126', 1, '126', 'Active' UNION ALL SELECT 'EMP-129', 1, '129', 'Active' UNION ALL
    SELECT 'EMP-137', 1, '137', 'Active' UNION ALL SELECT 'EMP-015', 1, '15',  'Active' UNION ALL
    SELECT 'EMP-052', 1, '52',  'Active' UNION ALL SELECT 'EMP-054', 1, '54',  'Active' UNION ALL
    SELECT 'EMP-055', 1, '55',  'Active' UNION ALL SELECT 'EMP-061', 1, '61',  'Active' UNION ALL
    SELECT 'EMP-078', 1, '78',  'Active' UNION ALL SELECT 'EMP-086', 1, '86',  'Active' UNION ALL
    SELECT 'EMP-092', 1, '92',  'Active' UNION ALL
    SELECT 'EMP-001', 1, '1',   'Active' UNION ALL SELECT 'EMP-108', 1, '108', 'Active' UNION ALL
    SELECT 'EMP-114', 1, '114', 'Active' UNION ALL SELECT 'EMP-121', 1, '121', 'Active' UNION ALL
    SELECT 'EMP-123', 1, '123', 'Active' UNION ALL SELECT 'EMP-124', 1, '124', 'Active' UNION ALL
    SELECT 'EMP-127', 1, '127', 'Active' UNION ALL SELECT 'EMP-013', 1, '13',  'Active' UNION ALL
    SELECT 'EMP-132', 1, '132', 'Active' UNION ALL SELECT 'EMP-134', 1, '134', 'Active' UNION ALL
    SELECT 'EMP-135', 1, '135', 'Active' UNION ALL SELECT 'EMP-136', 1, '136', 'Active' UNION ALL
    SELECT 'EMP-138', 1, '138', 'Active' UNION ALL SELECT 'EMP-019', 1, '19',  'Active' UNION ALL
    SELECT 'EMP-002', 1, '2',   'Active' UNION ALL SELECT 'EMP-021', 1, '21',  'Active' UNION ALL
    SELECT 'EMP-023', 1, '23',  'Active' UNION ALL SELECT 'EMP-024', 1, '24',  'Active' UNION ALL
    SELECT 'EMP-032', 1, '32',  'Active' UNION ALL SELECT 'EMP-053', 1, '53',  'Active' UNION ALL
    SELECT 'EMP-006', 1, '6',   'Active' UNION ALL SELECT 'EMP-065', 1, '65',  'Active' UNION ALL
    SELECT 'EMP-008', 1, '8',   'Active' UNION ALL SELECT 'EMP-084', 1, '84',  'Active' UNION ALL
    SELECT 'EMP-094', 1, '94',  'Active' UNION ALL SELECT 'EMP-095', 1, '95',  'Active' UNION ALL
    SELECT 'EMP-026', 1, '26',  'Active' UNION ALL SELECT 'EMP-027', 1, '27',  'Active' UNION ALL
    SELECT 'EMP-043', 1, '43',  'Active' UNION ALL SELECT 'EMP-070', 1, '70',  'Active' UNION ALL
    -- SURALLAH active
    SELECT 'EMP-106', 2, '106', 'Active' UNION ALL SELECT 'EMP-125', 2, '125', 'Active' UNION ALL
    SELECT 'EMP-020', 2, '20',  'Active' UNION ALL SELECT 'EMP-042', 2, '42',  'Active' UNION ALL
    SELECT 'EMP-059', 2, '59',  'Active' UNION ALL SELECT 'EMP-083', 2, '83',  'Active' UNION ALL
    SELECT 'EMP-103', 2, '103', 'Active' UNION ALL SELECT 'EMP-104', 2, '104', 'Active' UNION ALL
    SELECT 'EMP-058', 2, '58',  'Active' UNION ALL SELECT 'EMP-074', 2, '74',  'Active' UNION ALL
    SELECT 'EMP-089', 2, '89',  'Active' UNION ALL SELECT 'EMP-097', 2, '97',  'Active' UNION ALL
    SELECT 'EMP-017', 2, '17',  'Active' UNION ALL
    -- RESIGNED (Inactive enrollments on DEVICE-BANGA)
    SELECT 'EMP-010', 1, '10',  'Inactive' UNION ALL SELECT 'EMP-101', 1, '101', 'Inactive' UNION ALL
    SELECT 'EMP-102', 1, '102', 'Inactive' UNION ALL SELECT 'EMP-105', 1, '105', 'Inactive' UNION ALL
    SELECT 'EMP-107', 1, '107', 'Inactive' UNION ALL SELECT 'EMP-109', 1, '109', 'Inactive' UNION ALL
    SELECT 'EMP-011', 1, '11',  'Inactive' UNION ALL SELECT 'EMP-110', 1, '110', 'Inactive' UNION ALL
    SELECT 'EMP-111', 1, '111', 'Inactive' UNION ALL SELECT 'EMP-112', 1, '112', 'Inactive' UNION ALL
    SELECT 'EMP-113', 1, '113', 'Inactive' UNION ALL SELECT 'EMP-115', 1, '115', 'Inactive' UNION ALL
    SELECT 'EMP-116', 1, '116', 'Inactive' UNION ALL SELECT 'EMP-117', 1, '117', 'Inactive' UNION ALL
    SELECT 'EMP-118', 1, '118', 'Inactive' UNION ALL SELECT 'EMP-119', 1, '119', 'Inactive' UNION ALL
    SELECT 'EMP-012', 1, '12',  'Inactive' UNION ALL SELECT 'EMP-120', 1, '120', 'Inactive' UNION ALL
    SELECT 'EMP-122', 1, '122', 'Inactive' UNION ALL SELECT 'EMP-128', 1, '128', 'Inactive' UNION ALL
    SELECT 'EMP-130', 1, '130', 'Inactive' UNION ALL SELECT 'EMP-131', 1, '131', 'Inactive' UNION ALL
    SELECT 'EMP-133', 1, '133', 'Inactive' UNION ALL SELECT 'EMP-014', 1, '14',  'Inactive' UNION ALL
    SELECT 'EMP-016', 1, '16',  'Inactive' UNION ALL SELECT 'EMP-018', 1, '18',  'Inactive' UNION ALL
    SELECT 'EMP-022', 1, '22',  'Inactive' UNION ALL SELECT 'EMP-025', 1, '25',  'Inactive' UNION ALL
    SELECT 'EMP-028', 1, '28',  'Inactive' UNION ALL SELECT 'EMP-029', 1, '29',  'Inactive' UNION ALL
    SELECT 'EMP-003', 1, '3',   'Inactive' UNION ALL SELECT 'EMP-030', 1, '30',  'Inactive' UNION ALL
    SELECT 'EMP-031', 1, '31',  'Inactive' UNION ALL SELECT 'EMP-033', 1, '33',  'Inactive' UNION ALL
    SELECT 'EMP-034', 1, '34',  'Inactive' UNION ALL SELECT 'EMP-035', 1, '35',  'Inactive' UNION ALL
    SELECT 'EMP-036', 1, '36',  'Inactive' UNION ALL SELECT 'EMP-037', 1, '37',  'Inactive' UNION ALL
    SELECT 'EMP-038', 1, '38',  'Inactive' UNION ALL SELECT 'EMP-039', 1, '39',  'Inactive' UNION ALL
    SELECT 'EMP-004', 1, '4',   'Inactive' UNION ALL SELECT 'EMP-040', 1, '40',  'Inactive' UNION ALL
    SELECT 'EMP-041', 1, '41',  'Inactive' UNION ALL SELECT 'EMP-044', 1, '44',  'Inactive' UNION ALL
    SELECT 'EMP-045', 1, '45',  'Inactive' UNION ALL SELECT 'EMP-046', 1, '46',  'Inactive' UNION ALL
    SELECT 'EMP-047', 1, '47',  'Inactive' UNION ALL SELECT 'EMP-048', 1, '48',  'Inactive' UNION ALL
    SELECT 'EMP-049', 1, '49',  'Inactive' UNION ALL SELECT 'EMP-005', 1, '5',   'Inactive' UNION ALL
    SELECT 'EMP-050', 1, '50',  'Inactive' UNION ALL SELECT 'EMP-051', 1, '51',  'Inactive' UNION ALL
    SELECT 'EMP-056', 1, '56',  'Inactive' UNION ALL SELECT 'EMP-057', 1, '57',  'Inactive' UNION ALL
    SELECT 'EMP-060', 1, '60',  'Inactive' UNION ALL SELECT 'EMP-062', 1, '62',  'Inactive' UNION ALL
    SELECT 'EMP-063', 1, '63',  'Inactive' UNION ALL SELECT 'EMP-064', 1, '64',  'Inactive' UNION ALL
    SELECT 'EMP-066', 1, '66',  'Inactive' UNION ALL SELECT 'EMP-067', 1, '67',  'Inactive' UNION ALL
    SELECT 'EMP-068', 1, '68',  'Inactive' UNION ALL SELECT 'EMP-069', 1, '69',  'Inactive' UNION ALL
    SELECT 'EMP-007', 1, '7',   'Inactive' UNION ALL SELECT 'EMP-071', 1, '71',  'Inactive' UNION ALL
    SELECT 'EMP-072', 1, '72',  'Inactive' UNION ALL SELECT 'EMP-073', 1, '73',  'Inactive' UNION ALL
    SELECT 'EMP-075', 1, '75',  'Inactive' UNION ALL SELECT 'EMP-076', 1, '76',  'Inactive' UNION ALL
    SELECT 'EMP-077', 1, '77',  'Inactive' UNION ALL SELECT 'EMP-079', 1, '79',  'Inactive' UNION ALL
    SELECT 'EMP-080', 1, '80',  'Inactive' UNION ALL SELECT 'EMP-081', 1, '81',  'Inactive' UNION ALL
    SELECT 'EMP-082', 1, '82',  'Inactive' UNION ALL SELECT 'EMP-085', 1, '85',  'Inactive' UNION ALL
    SELECT 'EMP-087', 1, '87',  'Inactive' UNION ALL SELECT 'EMP-088', 1, '88',  'Inactive' UNION ALL
    SELECT 'EMP-009', 1, '9',   'Inactive' UNION ALL SELECT 'EMP-090', 1, '90',  'Inactive' UNION ALL
    SELECT 'EMP-091', 1, '91',  'Inactive' UNION ALL SELECT 'EMP-093', 1, '93',  'Inactive' UNION ALL
    SELECT 'EMP-096', 1, '96',  'Inactive' UNION ALL SELECT 'EMP-098', 1, '98',  'Inactive' UNION ALL
    SELECT 'EMP-099', 1, '99',  'Inactive'
) AS m ON m.emp_num = e.employee_number;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- Verification counts
-- =============================================================================
SELECT 'employee'                    AS tbl, COUNT(*) AS row_count FROM employee
UNION ALL
SELECT 'employee_branch_assignment',         COUNT(*) FROM employee_branch_assignment
UNION ALL
SELECT 'employee_biometric_enrollment',      COUNT(*) FROM employee_biometric_enrollment;
