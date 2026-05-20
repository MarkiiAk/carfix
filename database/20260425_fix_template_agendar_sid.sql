-- Fix: template_agendar_sid apuntaba al template simple (HX183daf...) en lugar del
-- template de horarios con variables individuales por slot (HX2c893...).
-- El template correcto tiene {{1}}=nombre, {{2}}-{{9}}=slots individuales, {{10}}="9. Otro horario"

UPDATE twilio_config
SET config_value = 'HX2c89326481fdc97a27d7cb3aa8a873a4',
    description  = 'ContentSid del template de horarios con variables individuales por slot ({{1}}=nombre, {{2}}-{{9}}=slots, {{10}}=otro horario)'
WHERE config_key = 'template_agendar_sid';
