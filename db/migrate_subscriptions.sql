-- Migración: Sistema de Suscripciones
-- Añade la columna para los 3 niveles: free, pro, elite.
-- Por defecto todos los usuarios inician en 'free'

ALTER TABLE users 
ADD COLUMN IF NOT EXISTS subscription_tier ENUM('free', 'pro', 'elite') DEFAULT 'free';
