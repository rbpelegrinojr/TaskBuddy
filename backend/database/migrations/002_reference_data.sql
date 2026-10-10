-- Reference data (not user data): default matching configuration, categories and skills.
INSERT INTO matching_configurations (version, algorithm_version, weight_category, weight_skills, weight_availability, weight_budget, is_active, note)
VALUES (1, '1.0.0', 30, 40, 20, 10, 1, 'Initial weighting model');

INSERT INTO service_categories (name, description) VALUES
 ('Home Repair', 'Plumbing, electrical and general repair work'),
 ('Cleaning', 'Home and office cleaning services'),
 ('Tutoring', 'Academic tutoring and coaching'),
 ('Graphic Design', 'Logos, posters and digital artwork'),
 ('Web Development', 'Websites and web applications'),
 ('Writing and Translation', 'Content writing, editing and translation');

INSERT INTO skills (name) VALUES
 ('Plumbing'), ('Electrical Wiring'), ('Carpentry'), ('Painting'),
 ('Deep Cleaning'), ('Laundry'),
 ('Mathematics'), ('English'), ('Science'),
 ('Logo Design'), ('Photo Editing'), ('Layout Design'),
 ('HTML/CSS'), ('PHP'), ('JavaScript'), ('MySQL'),
 ('Copywriting'), ('Proofreading'), ('Translation');

INSERT INTO category_skills (category_id, skill_id)
SELECT c.id, s.id FROM service_categories c JOIN skills s ON
 (c.name='Home Repair' AND s.name IN ('Plumbing','Electrical Wiring','Carpentry','Painting')) OR
 (c.name='Cleaning' AND s.name IN ('Deep Cleaning','Laundry')) OR
 (c.name='Tutoring' AND s.name IN ('Mathematics','English','Science')) OR
 (c.name='Graphic Design' AND s.name IN ('Logo Design','Photo Editing','Layout Design')) OR
 (c.name='Web Development' AND s.name IN ('HTML/CSS','PHP','JavaScript','MySQL')) OR
 (c.name='Writing and Translation' AND s.name IN ('Copywriting','Proofreading','Translation','English'));
