<?php
return ['default_expiry_days'=>(int) env('QUOTATION_DEFAULT_EXPIRY_DAYS',30),'otp_expiry_minutes'=>(int) env('OTP_EXPIRY_MINUTES',15),'session_idle_minutes'=>(int) env('SESSION_IDLE_MINUTES',30)];
