<?php

/*
|--------------------------------------------------------------------------
| Load The Cached Routes
|--------------------------------------------------------------------------
|
| Here we will decode and unserialize the RouteCollection instance that
| holds all of the route information for an application. This allows
| us to instantaneously load the entire route map into the router.
|
*/

app('router')->setCompiledRoutes(
    array (
  'compiled' => 
  array (
    0 => false,
    1 => 
    array (
      '/sanctum/csrf-cookie' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'sanctum.csrf-cookie',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/_ignition/health-check' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'ignition.healthCheck',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/_ignition/execute-solution' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'ignition.executeSolution',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/_ignition/update-config' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'ignition.updateConfig',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::aAMm8GCMXtQWQAb6',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/health' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::DBN3FijiZtjChC3G',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/live' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::vNKkE5bd9kFa3iyw',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/health' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::SszLjZcevdJHx6yW',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/ready' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::TLOxLPuEsrRuviyK',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/register' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::pl3HmIj2c2jkNnhI',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/login' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::xqJRwyHLFSY07jEY',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/auth/google' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::TErLgFJZMbXrVWar',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/auth/google/callback' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::zCcRJqLYSI1n8j0a',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/auth/google/exchange-ticket' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::YcpDeLZPyu2TB9iS',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/integrations/academic-risk/webhook' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::UIMT5F3Ni2945Qlc',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/push/vapid-public-key' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::8ydg4NLHz60JpOU7',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/logout' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::SaFb14faAYDS0vNe',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/refresh' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::PEXveFLbja1lFb4t',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/me' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::YZAAEgWdktjdwbCS',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/me/presence' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::hrUV0ul5P2lmH3kS',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/auth/sessions' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::bRnfRTmZ2e75XgNy',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/auth/2fa/status' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::MhdjTdoVKe10aHMp',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/auth/2fa/setup' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::udLITfnC1uqia74Y',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/auth/2fa/verify' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::DT5qeZvuTDqsfJDI',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/tips/today' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::dcC0b6EkI79xGO2i',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/wellness/tip' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::ajHkUNxYbfa6Vhh6',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/wellness/tips/favorites' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::TOKz8CBIvMrCIoLf',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/sessions/chat-list' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::k2SZbOggVx9ffqAn',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/sessions' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'sessions.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'sessions.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/chat/messages' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::bVdYb7MpRcp1yFd9',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/chat/incoming-digest' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::4fWSpkAlE5HMBNid',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/chat/upload-file' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::xaCu94YUaO9HasFp',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/appointments/bulk-cancel' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::j6Vxav0Ehyalx0Ca',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/appointments' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'appointments.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'appointments.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/intake-submissions' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::TruJbdD3nUs7Pyt7',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::Q07myDJUA9X8CUHM',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/referrals' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::D9Z9zKQ9B9n7qtsc',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::83qberHqOpGogobE',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/notifications' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::PjJkrqlCEpxbftz7',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::oWagi9lnIqIxgOWF',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/notifications/read-all' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::LgeGUwxbrA16ozie',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/push/subscribe' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::eQeNfJ7HxnHeJrqG',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/push/unsubscribe' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::vU3xpQfVgbT647ro',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/push/preferences' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::jLKXw4qYRfYATyGK',
          ),
          1 => NULL,
          2 => 
          array (
            'PATCH' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/analytics/overview' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::dF3rGDqrt9Jb0Qx5',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/analytics/dashboard' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::jMZQJRYnrGFFA9XL',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/analytics/export' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::ksodC7FUiEqtNxqy',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/video-calls/authorize' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::EEDTOHLrAEwoOCXH',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/video-calls/cancel' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::H4DGrklmL87m4Yev',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/video-calls/end' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::yT6evDVuuKhtp5Ww',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/counselor/incoming-calls' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::uKipF7EWxM3DSlFn',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/student/incoming-calls' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::BnKPoZr8b9APYUTS',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/counselor/session-reminders' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::RBnrWs4GMZHLEypo',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/users' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::wRWG5LBuuJVk7m3w',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/users/counselors' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::65jEDzxSpXmPalec',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/users/peer-counselors' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::XcUSYXSj1IumbDiJ',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/users/students' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::hDrGXpFMI4Th1gKm',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/users/counselors/approve-bulk' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::QACh9oH3gkUaeCqa',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/peer/dashboard' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::gTDMobWQJJ299jvV',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/peer/escalations' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::aLJXJqD1qQXR7Jjo',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/peer/availability' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::dpVU3dvdWWacHQm4',
          ),
          1 => NULL,
          2 => 
          array (
            'PATCH' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/institution-accounts' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::03ifOvUUueQU4XRM',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::B6fYPJ0C8g5KQ65C',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/profile' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::uVFzXuO7RAQHwfjk',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::hwwJBTZNq5H8SK3k',
          ),
          1 => NULL,
          2 => 
          array (
            'PUT' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/ai/wellness-chat/history' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::66BmX5oC7dvNpcoB',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/ai/wellness-chat' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::3sqJJ4FwZLK0ZXiS',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/ai-diagnostics' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::M3kNKep2pdk8AARJ',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/ai-diagnostics/summary' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::nqWXEUEvnMACbPx2',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/ai-diagnostics/latest' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::09vFgejCKR4JtGvZ',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/diagnostics/questionnaire' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::anlV0CbaRgDLHYjq',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/diagnostics/analyze' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::F4mFmAZEpVx7I83V',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/diagnostics/history' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::77V92WMlIfEjgQWS',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/diagnostics/latest' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::3iqHUdFuRcjbfiZc',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/diagnostics/trends' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::9WWNW6O30mMZvdkz',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/diagnostics/counselor-dashboard' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::o7SuzIWEY3YN148j',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/student-wellness/summary' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::08vZCIa1K1UCNA8b',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/ml/counselor-matches' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::oLYN1FSOPiAUOPXz',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/ml/health' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::KKD3fKvDB8VAlcwz',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/student-mood/today' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::Iu9EfZse3jdPWMZ7',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/student-mood' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::DKQA2aWLzmQLg6Eq',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/counselor-wellness' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::sWbsdJsE0CvkxCVj',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::JLJQmaXRuaCvBPmS',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/counselor-wellness/summary' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::y7XfUkUwBVtjyVR7',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/counselor-wellness/health-check' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::YH56mCklO5RqAuTq',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/panic-logs' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::ZOXBvEXg8QQPt8AR',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::OMTdA98kcA8PH7qu',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/settings' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::OlTJEDUxtaWhWAJF',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::Ugk2alYxYSF9oMgs',
          ),
          1 => NULL,
          2 => 
          array (
            'PUT' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/settings/clear-cache' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::TTHL7rqe49UzLtIi',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/tips' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::hYnMpszPlF6kYl9N',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::2JUYRKLLSpOJ489d',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/admin/add-tip' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::FJzJpUXhrUWZOvli',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/backups' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::ZktimhuKTedNkepb',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/backups/verify' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::a85mFMuRWG1vMiuU',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/backups/drill' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::G5ZdbNi8LWYKmFkI',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/activity-logs' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::NWzoMMHlDnwfNEo9',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/activity-logs/stream' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::dy72BC2zXqrGqeF0',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/activity-logs/stats' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::4a3yFfHccGiyGQzt',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/data-access-logs' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::w7nc6OX2iwhOtsYH',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/ai-reports' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::4nf8eqJVOGt7GKa8',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/integrations/academic-risk/events' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::ropiCxJFrrNWMeSS',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/integrations/academic-risk/runs' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::GptAdXXaPZXtDUpQ',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/openrouter/chat' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::6sWOi8HrmF08AS2v',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/openrouter/stream' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::nOSoyyKvd1OIH56u',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/openrouter/simple-chat' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::86nd8vgA9dYYxXXj',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/openrouter/models' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::MIRpeXgpM0a7By2Z',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/openrouter/conversations' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::zGgAtBNIafcSJOGM',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::ffG6WisYjoi1webz',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
    ),
    2 => 
    array (
      0 => '{^(?|/api/(?|c(?|hat/files/([^/]++)/content(*:45)|ounselor/incoming\\-calls/([^/]++)(*:85))|a(?|uth/sessions/(?|([^/]++)(*:121)|logout\\-others(*:143))|ppointments/([^/]++)(?|(*:175)|/analyze(*:191))|i\\-(?|diagnostics/([^/]++)(*:226)|reports/(?|([^/]++)(*:253)|generate(*:269)|([^/]++)(*:285)))|dmin/(?|update\\-tip/([^/]++)(*:323)|delete\\-tip/([^/]++)(*:351)))|wellness/tips/([^/]++)/favorite(?|(*:395))|s(?|essions/(?|([^/]++)(?|/(?|c(?|hat\\-anonymity(*:452)|risis\\-signal(*:473))|messages(?|(*:493)|/(?|read(*:509)|([^/]++)(*:525))|(*:534))|typing(?|(*:552))|attachments(*:572))|(*:581))|counselor(*:599)|([^/]++)/(?|note(?|(*:626))|a(?|ssign\\-peer(*:650)|nalyze(*:664))|unassign\\-peer(*:687)|escalate(*:703)|panic\\-escalate(*:726)|flag\\-urgent(*:746)|reveal\\-identity(*:770)|voice\\-notes(*:790)))|tudent/incoming\\-calls/([^/]++)(*:831))|messages/([^/]++)/(?|attachment(*:871)|voice\\-note(*:890))|in(?|take\\-submissions/([^/]++)(*:930)|stitution\\-accounts/([^/]++)(?|(*:969)))|r(?|isk\\-alerts/([^/]++)/acknowledge(*:1015)|eferrals/([^/]++)(?|(*:1044)|/events(*:1060)))|notifications/([^/]++)/read(*:1098)|users/counselors/([^/]++)(?|/(?|approve(*:1146)|reject(*:1161))|(*:1171))|panic\\-logs/([^/]++)(*:1201)|tips/([^/]++)(?|(*:1226))|openrouter/conversations/([0-9]+)(?|(*:1272))))/?$}sDu',
    ),
    3 => 
    array (
      45 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'chat-files.content',
          ),
          1 => 
          array (
            0 => 'chatFile',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      85 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::BlwObTHyXAjhImKk',
          ),
          1 => 
          array (
            0 => 'counselingCall',
          ),
          2 => 
          array (
            'PATCH' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      121 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::dPmslu2FZj6FugY6',
          ),
          1 => 
          array (
            0 => 'sessionId',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      143 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::g35EIjPQTAivVgz2',
          ),
          1 => 
          array (
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      175 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'appointments.show',
          ),
          1 => 
          array (
            0 => 'appointment',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'appointments.update',
          ),
          1 => 
          array (
            0 => 'appointment',
          ),
          2 => 
          array (
            'PUT' => 0,
            'PATCH' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'appointments.destroy',
          ),
          1 => 
          array (
            0 => 'appointment',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      191 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::hFZyOMmjXT1NJDfG',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      226 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::Lcs7waKRCBVpsXGE',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      253 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::bSQa8VrpcXR87A6u',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      269 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::oJU2oAwXtvCfcVL5',
          ),
          1 => 
          array (
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      285 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::xq5UklS638m1LNDu',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      323 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::NnxVtEk8GGmGR8Ji',
          ),
          1 => 
          array (
            0 => 'tip',
          ),
          2 => 
          array (
            'PUT' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      351 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::mdMcFsp49P0UVbry',
          ),
          1 => 
          array (
            0 => 'tip',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      395 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::eg6O2kEWfBeUKJXZ',
          ),
          1 => 
          array (
            0 => 'tip',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::zwJ4SeSCev0YzIf7',
          ),
          1 => 
          array (
            0 => 'tip',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      452 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::xsuyrJMwb7uYsOzs',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'PATCH' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      473 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::wPi6LOQnJvf988z5',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      493 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::OBVfz1iglxSsOIGl',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      509 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::YCEQaWPl8hdv1jGx',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      525 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::2ANUUUYOcZKqo2iX',
          ),
          1 => 
          array (
            0 => 'id',
            1 => 'messageId',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      534 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::AIG6ZVt4IGC6VwtU',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      552 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::4q1izA5APSaLiZqY',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::VBx4KadCv0xMwk8U',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      572 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::x4aYJUgUq2NmX5ix',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      581 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'sessions.show',
          ),
          1 => 
          array (
            0 => 'session',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'sessions.update',
          ),
          1 => 
          array (
            0 => 'session',
          ),
          2 => 
          array (
            'PUT' => 0,
            'PATCH' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'sessions.destroy',
          ),
          1 => 
          array (
            0 => 'session',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      599 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::rU6wbYAXeh6yqmVL',
          ),
          1 => 
          array (
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      626 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::GAFzoeTT67EXl19K',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'PUT' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::UcoPZm48gfACdLVz',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      650 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::9ix28C3dYnG7w4WK',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      664 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::PV6hHeENCPguuPb4',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      687 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::q519Ng8a8WkCoQrR',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      703 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::q4pJvF5oujGb1KZs',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      726 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::g8SK0L4N2LtOQ5NN',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      746 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::vnLfWnuaTYMC1YYI',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      770 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::0jTJQBbgqQP3J0fo',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      790 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::wrKmHJVPoGnKdGjX',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      831 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::WqeLgNQezojZiEs4',
          ),
          1 => 
          array (
            0 => 'counselingCall',
          ),
          2 => 
          array (
            'PATCH' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      871 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::OEvkRrieba5ZrBuw',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      890 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::OSF10cr1RUSPtSZg',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      930 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::HEDBD3K324QbwAyZ',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      969 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::Q0dVWLjWGydp4CPJ',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'PUT' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::DsZ8W9vBmBeYVBCi',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1015 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::q0pURjlV1qw24P0q',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'PATCH' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      1044 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::HO2Qb3oIgxJUzf0g',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::Bdy1gSABQq2Ntr5D',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'PATCH' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1060 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::GRU86wTkdC6LPJmJ',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      1098 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::IsbGD1g4JnDFwXpG',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'PATCH' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      1146 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::dC7LVzr3zmwW6gcX',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      1161 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::D0oCrSRb4aCLYbaF',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      1171 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::iVXy7cOuXF01llLv',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1201 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::Bb3NyWdEDbyUz4x4',
          ),
          1 => 
          array (
            0 => 'id',
          ),
          2 => 
          array (
            'PUT' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1226 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::zgNU2HAUEFA8x4hT',
          ),
          1 => 
          array (
            0 => 'tip',
          ),
          2 => 
          array (
            'PUT' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::Nbwp0YaInVAiZo2w',
          ),
          1 => 
          array (
            0 => 'tip',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      1272 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::rNTU9f5FJBDhtcnL',
          ),
          1 => 
          array (
            0 => 'conversationId',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'generated::yfHgHjvEZwkjjHL6',
          ),
          1 => 
          array (
            0 => 'conversationId',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => NULL,
          1 => NULL,
          2 => NULL,
          3 => NULL,
          4 => false,
          5 => false,
          6 => 0,
        ),
      ),
    ),
    4 => NULL,
  ),
  'attributes' => 
  array (
    'sanctum.csrf-cookie' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'sanctum/csrf-cookie',
      'action' => 
      array (
        'uses' => 'Laravel\\Sanctum\\Http\\Controllers\\CsrfCookieController@show',
        'controller' => 'Laravel\\Sanctum\\Http\\Controllers\\CsrfCookieController@show',
        'namespace' => NULL,
        'prefix' => 'sanctum',
        'where' => 
        array (
        ),
        'middleware' => 
        array (
          0 => 'web',
        ),
        'as' => 'sanctum.csrf-cookie',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'ignition.healthCheck' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => '_ignition/health-check',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'Spatie\\LaravelIgnition\\Http\\Middleware\\RunnableSolutionsEnabled',
        ),
        'uses' => 'Spatie\\LaravelIgnition\\Http\\Controllers\\HealthCheckController@__invoke',
        'controller' => 'Spatie\\LaravelIgnition\\Http\\Controllers\\HealthCheckController',
        'as' => 'ignition.healthCheck',
        'namespace' => NULL,
        'prefix' => '_ignition',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'ignition.executeSolution' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => '_ignition/execute-solution',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'Spatie\\LaravelIgnition\\Http\\Middleware\\RunnableSolutionsEnabled',
        ),
        'uses' => 'Spatie\\LaravelIgnition\\Http\\Controllers\\ExecuteSolutionController@__invoke',
        'controller' => 'Spatie\\LaravelIgnition\\Http\\Controllers\\ExecuteSolutionController',
        'as' => 'ignition.executeSolution',
        'namespace' => NULL,
        'prefix' => '_ignition',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'ignition.updateConfig' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => '_ignition/update-config',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'Spatie\\LaravelIgnition\\Http\\Middleware\\RunnableSolutionsEnabled',
        ),
        'uses' => 'Spatie\\LaravelIgnition\\Http\\Controllers\\UpdateConfigController@__invoke',
        'controller' => 'Spatie\\LaravelIgnition\\Http\\Controllers\\UpdateConfigController',
        'as' => 'ignition.updateConfig',
        'namespace' => NULL,
        'prefix' => '_ignition',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::aAMm8GCMXtQWQAb6' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => '/',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
        ),
        'uses' => 'App\\Http\\Controllers\\HealthController@root',
        'controller' => 'App\\Http\\Controllers\\HealthController@root',
        'namespace' => NULL,
        'prefix' => '',
        'where' => 
        array (
        ),
        'as' => 'generated::aAMm8GCMXtQWQAb6',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::DBN3FijiZtjChC3G' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'health',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
        ),
        'uses' => 'App\\Http\\Controllers\\HealthController@ready',
        'controller' => 'App\\Http\\Controllers\\HealthController@ready',
        'namespace' => NULL,
        'prefix' => '',
        'where' => 
        array (
        ),
        'as' => 'generated::DBN3FijiZtjChC3G',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::vNKkE5bd9kFa3iyw' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'live',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
        ),
        'uses' => 'App\\Http\\Controllers\\HealthController@health',
        'controller' => 'App\\Http\\Controllers\\HealthController@health',
        'namespace' => NULL,
        'prefix' => '',
        'where' => 
        array (
        ),
        'as' => 'generated::vNKkE5bd9kFa3iyw',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::SszLjZcevdJHx6yW' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/health',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
        ),
        'uses' => 'App\\Http\\Controllers\\HealthController@health',
        'controller' => 'App\\Http\\Controllers\\HealthController@health',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::SszLjZcevdJHx6yW',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::TLOxLPuEsrRuviyK' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/ready',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
        ),
        'uses' => 'App\\Http\\Controllers\\HealthController@ready',
        'controller' => 'App\\Http\\Controllers\\HealthController@ready',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::TLOxLPuEsrRuviyK',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::pl3HmIj2c2jkNnhI' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/register',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'throttle:auth-register',
        ),
        'uses' => 'App\\Http\\Controllers\\AuthController@register',
        'controller' => 'App\\Http\\Controllers\\AuthController@register',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::pl3HmIj2c2jkNnhI',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::xqJRwyHLFSY07jEY' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/login',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'throttle:auth-login',
        ),
        'uses' => 'App\\Http\\Controllers\\AuthController@login',
        'controller' => 'App\\Http\\Controllers\\AuthController@login',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::xqJRwyHLFSY07jEY',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'chat-files.content' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/chat/files/{chatFile}/content',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'signed',
        ),
        'uses' => 'App\\Http\\Controllers\\ChatAttachmentController@show',
        'controller' => 'App\\Http\\Controllers\\ChatAttachmentController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'chat-files.content',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::TErLgFJZMbXrVWar' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/auth/google',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'web',
        ),
        'uses' => 'App\\Http\\Controllers\\OAuthController@redirectToGoogle',
        'controller' => 'App\\Http\\Controllers\\OAuthController@redirectToGoogle',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::TErLgFJZMbXrVWar',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::zCcRJqLYSI1n8j0a' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/auth/google/callback',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'web',
        ),
        'uses' => 'App\\Http\\Controllers\\OAuthController@handleGoogleCallback',
        'controller' => 'App\\Http\\Controllers\\OAuthController@handleGoogleCallback',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::zCcRJqLYSI1n8j0a',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::YcpDeLZPyu2TB9iS' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/auth/google/exchange-ticket',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'throttle:oauth-ticket-exchange',
        ),
        'uses' => 'App\\Http\\Controllers\\OAuthController@exchangeLoginTicket',
        'controller' => 'App\\Http\\Controllers\\OAuthController@exchangeLoginTicket',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::YcpDeLZPyu2TB9iS',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::UIMT5F3Ni2945Qlc' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/integrations/academic-risk/webhook',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'verify.integration.signature',
          2 => 'throttle:120,1',
        ),
        'uses' => 'App\\Http\\Controllers\\AcademicRiskWebhookController@ingest',
        'controller' => 'App\\Http\\Controllers\\AcademicRiskWebhookController@ingest',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::UIMT5F3Ni2945Qlc',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::8ydg4NLHz60JpOU7' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/push/vapid-public-key',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
        ),
        'uses' => 'App\\Http\\Controllers\\PushSubscriptionController@vapidPublicKey',
        'controller' => 'App\\Http\\Controllers\\PushSubscriptionController@vapidPublicKey',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::8ydg4NLHz60JpOU7',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::SaFb14faAYDS0vNe' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/logout',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\AuthController@logout',
        'controller' => 'App\\Http\\Controllers\\AuthController@logout',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::SaFb14faAYDS0vNe',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::PEXveFLbja1lFb4t' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/refresh',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\AuthController@refresh',
        'controller' => 'App\\Http\\Controllers\\AuthController@refresh',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::PEXveFLbja1lFb4t',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::YZAAEgWdktjdwbCS' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/me',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\AuthController@me',
        'controller' => 'App\\Http\\Controllers\\AuthController@me',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::YZAAEgWdktjdwbCS',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::hrUV0ul5P2lmH3kS' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/me/presence',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:presence',
        ),
        'uses' => 'App\\Http\\Controllers\\AuthController@presence',
        'controller' => 'App\\Http\\Controllers\\AuthController@presence',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::hrUV0ul5P2lmH3kS',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::bRnfRTmZ2e75XgNy' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/auth/sessions',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\AuthSessionController@index',
        'controller' => 'App\\Http\\Controllers\\AuthSessionController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::bRnfRTmZ2e75XgNy',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::dPmslu2FZj6FugY6' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/auth/sessions/{sessionId}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\AuthSessionController@destroy',
        'controller' => 'App\\Http\\Controllers\\AuthSessionController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::dPmslu2FZj6FugY6',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::g35EIjPQTAivVgz2' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/auth/sessions/logout-others',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\AuthSessionController@logoutOtherDevices',
        'controller' => 'App\\Http\\Controllers\\AuthSessionController@logoutOtherDevices',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::g35EIjPQTAivVgz2',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::MhdjTdoVKe10aHMp' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/auth/2fa/status',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\CounselorTwoFactorController@status',
        'controller' => 'App\\Http\\Controllers\\CounselorTwoFactorController@status',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::MhdjTdoVKe10aHMp',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::udLITfnC1uqia74Y' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/auth/2fa/setup',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:auth-login',
        ),
        'uses' => 'App\\Http\\Controllers\\CounselorTwoFactorController@setup',
        'controller' => 'App\\Http\\Controllers\\CounselorTwoFactorController@setup',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::udLITfnC1uqia74Y',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::DT5qeZvuTDqsfJDI' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/auth/2fa/verify',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:auth-login',
        ),
        'uses' => 'App\\Http\\Controllers\\CounselorTwoFactorController@verify',
        'controller' => 'App\\Http\\Controllers\\CounselorTwoFactorController@verify',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::DT5qeZvuTDqsfJDI',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::dcC0b6EkI79xGO2i' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/tips/today',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\TipController@today',
        'controller' => 'App\\Http\\Controllers\\TipController@today',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::dcC0b6EkI79xGO2i',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::ajHkUNxYbfa6Vhh6' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/wellness/tip',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\TipController@wellnessTip',
        'controller' => 'App\\Http\\Controllers\\TipController@wellnessTip',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::ajHkUNxYbfa6Vhh6',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::TOKz8CBIvMrCIoLf' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/wellness/tips/favorites',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\TipController@favorites',
        'controller' => 'App\\Http\\Controllers\\TipController@favorites',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::TOKz8CBIvMrCIoLf',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::eg6O2kEWfBeUKJXZ' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/wellness/tips/{tip}/favorite',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\TipController@favorite',
        'controller' => 'App\\Http\\Controllers\\TipController@favorite',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::eg6O2kEWfBeUKJXZ',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::zwJ4SeSCev0YzIf7' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/wellness/tips/{tip}/favorite',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\TipController@unfavorite',
        'controller' => 'App\\Http\\Controllers\\TipController@unfavorite',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::zwJ4SeSCev0YzIf7',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::k2SZbOggVx9ffqAn' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/sessions/chat-list',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:messages-read',
        ),
        'uses' => 'App\\Http\\Controllers\\SessionController@chatList',
        'controller' => 'App\\Http\\Controllers\\SessionController@chatList',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::k2SZbOggVx9ffqAn',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::xsuyrJMwb7uYsOzs' => 
    array (
      'methods' => 
      array (
        0 => 'PATCH',
      ),
      'uri' => 'api/sessions/{id}/chat-anonymity',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:30,1',
        ),
        'uses' => 'App\\Http\\Controllers\\SessionController@updateChatAnonymity',
        'controller' => 'App\\Http\\Controllers\\SessionController@updateChatAnonymity',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::xsuyrJMwb7uYsOzs',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'sessions.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/sessions',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'as' => 'sessions.index',
        'uses' => 'App\\Http\\Controllers\\SessionController@index',
        'controller' => 'App\\Http\\Controllers\\SessionController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'sessions.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/sessions',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'as' => 'sessions.store',
        'uses' => 'App\\Http\\Controllers\\SessionController@store',
        'controller' => 'App\\Http\\Controllers\\SessionController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'sessions.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/sessions/{session}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'as' => 'sessions.show',
        'uses' => 'App\\Http\\Controllers\\SessionController@show',
        'controller' => 'App\\Http\\Controllers\\SessionController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'sessions.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
        1 => 'PATCH',
      ),
      'uri' => 'api/sessions/{session}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'as' => 'sessions.update',
        'uses' => 'App\\Http\\Controllers\\SessionController@update',
        'controller' => 'App\\Http\\Controllers\\SessionController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'sessions.destroy' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/sessions/{session}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'as' => 'sessions.destroy',
        'uses' => 'App\\Http\\Controllers\\SessionController@destroy',
        'controller' => 'App\\Http\\Controllers\\SessionController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::OBVfz1iglxSsOIGl' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/sessions/{id}/messages',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:messages-read',
        ),
        'uses' => 'App\\Http\\Controllers\\MessageController@index',
        'controller' => 'App\\Http\\Controllers\\MessageController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::OBVfz1iglxSsOIGl',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::YCEQaWPl8hdv1jGx' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/sessions/{id}/messages/read',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:messages-read',
        ),
        'uses' => 'App\\Http\\Controllers\\MessageController@markInboundRead',
        'controller' => 'App\\Http\\Controllers\\MessageController@markInboundRead',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::YCEQaWPl8hdv1jGx',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::bVdYb7MpRcp1yFd9' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/chat/messages',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:messages-read',
        ),
        'uses' => 'App\\Http\\Controllers\\MessageController@indexBySession',
        'controller' => 'App\\Http\\Controllers\\MessageController@indexBySession',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::bVdYb7MpRcp1yFd9',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::4fWSpkAlE5HMBNid' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/chat/incoming-digest',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:90,1',
        ),
        'uses' => 'App\\Http\\Controllers\\MessageController@incomingDigest',
        'controller' => 'App\\Http\\Controllers\\MessageController@incomingDigest',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::4fWSpkAlE5HMBNid',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::AIG6ZVt4IGC6VwtU' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/sessions/{id}/messages',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:messages-write',
        ),
        'uses' => 'App\\Http\\Controllers\\MessageController@store',
        'controller' => 'App\\Http\\Controllers\\MessageController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::AIG6ZVt4IGC6VwtU',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::wPi6LOQnJvf988z5' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/sessions/{id}/crisis-signal',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:20,1',
        ),
        'uses' => 'App\\Http\\Controllers\\MessageController@reportCrisisSignal',
        'controller' => 'App\\Http\\Controllers\\MessageController@reportCrisisSignal',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::wPi6LOQnJvf988z5',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::2ANUUUYOcZKqo2iX' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/sessions/{id}/messages/{messageId}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:messages-write',
        ),
        'uses' => 'App\\Http\\Controllers\\MessageController@destroy',
        'controller' => 'App\\Http\\Controllers\\MessageController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::2ANUUUYOcZKqo2iX',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::4q1izA5APSaLiZqY' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/sessions/{id}/typing',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:messages-write',
        ),
        'uses' => 'App\\Http\\Controllers\\MessageController@setTyping',
        'controller' => 'App\\Http\\Controllers\\MessageController@setTyping',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::4q1izA5APSaLiZqY',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::VBx4KadCv0xMwk8U' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/sessions/{id}/typing',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:messages-read',
        ),
        'uses' => 'App\\Http\\Controllers\\MessageController@typingStatus',
        'controller' => 'App\\Http\\Controllers\\MessageController@typingStatus',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::VBx4KadCv0xMwk8U',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::xaCu94YUaO9HasFp' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/chat/upload-file',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:messages-write',
        ),
        'uses' => 'App\\Http\\Controllers\\ChatAttachmentController@uploadForChat',
        'controller' => 'App\\Http\\Controllers\\ChatAttachmentController@uploadForChat',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::xaCu94YUaO9HasFp',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::x4aYJUgUq2NmX5ix' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/sessions/{id}/attachments',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:messages-write',
        ),
        'uses' => 'App\\Http\\Controllers\\ChatAttachmentController@upload',
        'controller' => 'App\\Http\\Controllers\\ChatAttachmentController@upload',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::x4aYJUgUq2NmX5ix',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::OEvkRrieba5ZrBuw' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/messages/{id}/attachment',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:messages-read',
        ),
        'uses' => 'App\\Http\\Controllers\\ChatAttachmentController@download',
        'controller' => 'App\\Http\\Controllers\\ChatAttachmentController@download',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::OEvkRrieba5ZrBuw',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::rU6wbYAXeh6yqmVL' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/sessions/counselor',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\SessionController@storeAsCounselor',
        'controller' => 'App\\Http\\Controllers\\SessionController@storeAsCounselor',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::rU6wbYAXeh6yqmVL',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::GAFzoeTT67EXl19K' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
      ),
      'uri' => 'api/sessions/{id}/note',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:60,1',
        ),
        'uses' => 'App\\Http\\Controllers\\SessionController@upsertNote',
        'controller' => 'App\\Http\\Controllers\\SessionController@upsertNote',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::GAFzoeTT67EXl19K',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::UcoPZm48gfACdLVz' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/sessions/{id}/note',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:60,1',
        ),
        'uses' => 'App\\Http\\Controllers\\SessionController@deleteNote',
        'controller' => 'App\\Http\\Controllers\\SessionController@deleteNote',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::UcoPZm48gfACdLVz',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::9ix28C3dYnG7w4WK' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/sessions/{id}/assign-peer',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:30,1',
        ),
        'uses' => 'App\\Http\\Controllers\\SessionController@assignPeerCounselor',
        'controller' => 'App\\Http\\Controllers\\SessionController@assignPeerCounselor',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::9ix28C3dYnG7w4WK',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::q519Ng8a8WkCoQrR' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/sessions/{id}/unassign-peer',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:30,1',
        ),
        'uses' => 'App\\Http\\Controllers\\SessionController@unassignPeerCounselor',
        'controller' => 'App\\Http\\Controllers\\SessionController@unassignPeerCounselor',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::q519Ng8a8WkCoQrR',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::q4pJvF5oujGb1KZs' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/sessions/{id}/escalate',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:30,1',
        ),
        'uses' => 'App\\Http\\Controllers\\SessionController@escalateToCounselor',
        'controller' => 'App\\Http\\Controllers\\SessionController@escalateToCounselor',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::q4pJvF5oujGb1KZs',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::g8SK0L4N2LtOQ5NN' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/sessions/{id}/panic-escalate',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:20,1',
        ),
        'uses' => 'App\\Http\\Controllers\\SessionController@panicEscalation',
        'controller' => 'App\\Http\\Controllers\\SessionController@panicEscalation',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::g8SK0L4N2LtOQ5NN',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::vnLfWnuaTYMC1YYI' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/sessions/{id}/flag-urgent',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:30,1',
        ),
        'uses' => 'App\\Http\\Controllers\\SessionController@flagUrgentConcern',
        'controller' => 'App\\Http\\Controllers\\SessionController@flagUrgentConcern',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::vnLfWnuaTYMC1YYI',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::0jTJQBbgqQP3J0fo' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/sessions/{id}/reveal-identity',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:10,1',
        ),
        'uses' => 'App\\Http\\Controllers\\SessionController@revealIdentity',
        'controller' => 'App\\Http\\Controllers\\SessionController@revealIdentity',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::0jTJQBbgqQP3J0fo',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::j6Vxav0Ehyalx0Ca' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/appointments/bulk-cancel',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:10,1',
        ),
        'uses' => 'App\\Http\\Controllers\\AppointmentController@bulkCancel',
        'controller' => 'App\\Http\\Controllers\\AppointmentController@bulkCancel',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::j6Vxav0Ehyalx0Ca',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'appointments.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/appointments',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'as' => 'appointments.index',
        'uses' => 'App\\Http\\Controllers\\AppointmentController@index',
        'controller' => 'App\\Http\\Controllers\\AppointmentController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'appointments.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/appointments',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'as' => 'appointments.store',
        'uses' => 'App\\Http\\Controllers\\AppointmentController@store',
        'controller' => 'App\\Http\\Controllers\\AppointmentController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'appointments.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/appointments/{appointment}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'as' => 'appointments.show',
        'uses' => 'App\\Http\\Controllers\\AppointmentController@show',
        'controller' => 'App\\Http\\Controllers\\AppointmentController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'appointments.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
        1 => 'PATCH',
      ),
      'uri' => 'api/appointments/{appointment}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'as' => 'appointments.update',
        'uses' => 'App\\Http\\Controllers\\AppointmentController@update',
        'controller' => 'App\\Http\\Controllers\\AppointmentController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'appointments.destroy' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/appointments/{appointment}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'as' => 'appointments.destroy',
        'uses' => 'App\\Http\\Controllers\\AppointmentController@destroy',
        'controller' => 'App\\Http\\Controllers\\AppointmentController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::TruJbdD3nUs7Pyt7' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/intake-submissions',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\IntakeController@index',
        'controller' => 'App\\Http\\Controllers\\IntakeController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::TruJbdD3nUs7Pyt7',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::Q07myDJUA9X8CUHM' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/intake-submissions',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\IntakeController@store',
        'controller' => 'App\\Http\\Controllers\\IntakeController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::Q07myDJUA9X8CUHM',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::HEDBD3K324QbwAyZ' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/intake-submissions/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\IntakeController@show',
        'controller' => 'App\\Http\\Controllers\\IntakeController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::HEDBD3K324QbwAyZ',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::q0pURjlV1qw24P0q' => 
    array (
      'methods' => 
      array (
        0 => 'PATCH',
      ),
      'uri' => 'api/risk-alerts/{id}/acknowledge',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:60,1',
        ),
        'uses' => 'App\\Http\\Controllers\\IntakeController@acknowledgeAlert',
        'controller' => 'App\\Http\\Controllers\\IntakeController@acknowledgeAlert',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::q0pURjlV1qw24P0q',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::D9Z9zKQ9B9n7qtsc' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/referrals',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\ReferralController@index',
        'controller' => 'App\\Http\\Controllers\\ReferralController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::D9Z9zKQ9B9n7qtsc',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::83qberHqOpGogobE' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/referrals',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\ReferralController@store',
        'controller' => 'App\\Http\\Controllers\\ReferralController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::83qberHqOpGogobE',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::HO2Qb3oIgxJUzf0g' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/referrals/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\ReferralController@show',
        'controller' => 'App\\Http\\Controllers\\ReferralController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::HO2Qb3oIgxJUzf0g',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::Bdy1gSABQq2Ntr5D' => 
    array (
      'methods' => 
      array (
        0 => 'PATCH',
      ),
      'uri' => 'api/referrals/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\ReferralController@update',
        'controller' => 'App\\Http\\Controllers\\ReferralController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::Bdy1gSABQq2Ntr5D',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::GRU86wTkdC6LPJmJ' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/referrals/{id}/events',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\ReferralController@addEvent',
        'controller' => 'App\\Http\\Controllers\\ReferralController@addEvent',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::GRU86wTkdC6LPJmJ',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::PjJkrqlCEpxbftz7' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/notifications',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\NotificationController@index',
        'controller' => 'App\\Http\\Controllers\\NotificationController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::PjJkrqlCEpxbftz7',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::oWagi9lnIqIxgOWF' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/notifications',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'admin',
        ),
        'uses' => 'App\\Http\\Controllers\\NotificationController@store',
        'controller' => 'App\\Http\\Controllers\\NotificationController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::oWagi9lnIqIxgOWF',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::IsbGD1g4JnDFwXpG' => 
    array (
      'methods' => 
      array (
        0 => 'PATCH',
      ),
      'uri' => 'api/notifications/{id}/read',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\NotificationController@markAsRead',
        'controller' => 'App\\Http\\Controllers\\NotificationController@markAsRead',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::IsbGD1g4JnDFwXpG',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::LgeGUwxbrA16ozie' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/notifications/read-all',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\NotificationController@markAllAsRead',
        'controller' => 'App\\Http\\Controllers\\NotificationController@markAllAsRead',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::LgeGUwxbrA16ozie',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::eQeNfJ7HxnHeJrqG' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/push/subscribe',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:60,1',
        ),
        'uses' => 'App\\Http\\Controllers\\PushSubscriptionController@subscribe',
        'controller' => 'App\\Http\\Controllers\\PushSubscriptionController@subscribe',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::eQeNfJ7HxnHeJrqG',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::vU3xpQfVgbT647ro' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/push/unsubscribe',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:60,1',
        ),
        'uses' => 'App\\Http\\Controllers\\PushSubscriptionController@unsubscribe',
        'controller' => 'App\\Http\\Controllers\\PushSubscriptionController@unsubscribe',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::vU3xpQfVgbT647ro',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::jLKXw4qYRfYATyGK' => 
    array (
      'methods' => 
      array (
        0 => 'PATCH',
      ),
      'uri' => 'api/push/preferences',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:30,1',
        ),
        'uses' => 'App\\Http\\Controllers\\PushSubscriptionController@preferences',
        'controller' => 'App\\Http\\Controllers\\PushSubscriptionController@preferences',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::jLKXw4qYRfYATyGK',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::dF3rGDqrt9Jb0Qx5' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/analytics/overview',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'admin',
        ),
        'uses' => 'App\\Http\\Controllers\\AnalyticsController@overview',
        'controller' => 'App\\Http\\Controllers\\AnalyticsController@overview',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::dF3rGDqrt9Jb0Qx5',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::jMZQJRYnrGFFA9XL' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/analytics/dashboard',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'admin',
        ),
        'uses' => 'App\\Http\\Controllers\\AnalyticsController@dashboard',
        'controller' => 'App\\Http\\Controllers\\AnalyticsController@dashboard',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::jMZQJRYnrGFFA9XL',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::ksodC7FUiEqtNxqy' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/analytics/export',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'admin',
        ),
        'uses' => 'App\\Http\\Controllers\\ReportExportController@export',
        'controller' => 'App\\Http\\Controllers\\ReportExportController@export',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::ksodC7FUiEqtNxqy',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::wrKmHJVPoGnKdGjX' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/sessions/{id}/voice-notes',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\VoiceNotesController@upload',
        'controller' => 'App\\Http\\Controllers\\VoiceNotesController@upload',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::wrKmHJVPoGnKdGjX',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::OSF10cr1RUSPtSZg' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/messages/{id}/voice-note',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\VoiceNotesController@download',
        'controller' => 'App\\Http\\Controllers\\VoiceNotesController@download',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::OSF10cr1RUSPtSZg',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::EEDTOHLrAEwoOCXH' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/video-calls/authorize',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:20,1',
        ),
        'uses' => 'App\\Http\\Controllers\\VideoCallController@authorizeCall',
        'controller' => 'App\\Http\\Controllers\\VideoCallController@authorizeCall',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::EEDTOHLrAEwoOCXH',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::H4DGrklmL87m4Yev' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/video-calls/cancel',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:30,1',
        ),
        'uses' => 'App\\Http\\Controllers\\VideoCallController@cancelCall',
        'controller' => 'App\\Http\\Controllers\\VideoCallController@cancelCall',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::H4DGrklmL87m4Yev',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::yT6evDVuuKhtp5Ww' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/video-calls/end',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:20,1',
        ),
        'uses' => 'App\\Http\\Controllers\\VideoCallController@end',
        'controller' => 'App\\Http\\Controllers\\VideoCallController@end',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::yT6evDVuuKhtp5Ww',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::uKipF7EWxM3DSlFn' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/counselor/incoming-calls',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'counselor',
          8 => 'throttle:120,1',
        ),
        'uses' => 'App\\Http\\Controllers\\CounselorIncomingCallController@index',
        'controller' => 'App\\Http\\Controllers\\CounselorIncomingCallController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::uKipF7EWxM3DSlFn',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::BlwObTHyXAjhImKk' => 
    array (
      'methods' => 
      array (
        0 => 'PATCH',
      ),
      'uri' => 'api/counselor/incoming-calls/{counselingCall}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'counselor',
          8 => 'throttle:60,1',
        ),
        'uses' => 'App\\Http\\Controllers\\CounselorIncomingCallController@update',
        'controller' => 'App\\Http\\Controllers\\CounselorIncomingCallController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::BlwObTHyXAjhImKk',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::BnKPoZr8b9APYUTS' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/student/incoming-calls',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'student',
          8 => 'throttle:120,1',
        ),
        'uses' => 'App\\Http\\Controllers\\StudentIncomingCallController@index',
        'controller' => 'App\\Http\\Controllers\\StudentIncomingCallController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::BnKPoZr8b9APYUTS',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::WqeLgNQezojZiEs4' => 
    array (
      'methods' => 
      array (
        0 => 'PATCH',
      ),
      'uri' => 'api/student/incoming-calls/{counselingCall}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'student',
          8 => 'throttle:60,1',
        ),
        'uses' => 'App\\Http\\Controllers\\StudentIncomingCallController@update',
        'controller' => 'App\\Http\\Controllers\\StudentIncomingCallController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::WqeLgNQezojZiEs4',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::RBnrWs4GMZHLEypo' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/counselor/session-reminders',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'counselor',
          8 => 'throttle:90,1',
        ),
        'uses' => 'App\\Http\\Controllers\\CounselorSessionReminderController@index',
        'controller' => 'App\\Http\\Controllers\\CounselorSessionReminderController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::RBnrWs4GMZHLEypo',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::wRWG5LBuuJVk7m3w' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/users',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'admin',
        ),
        'uses' => 'App\\Http\\Controllers\\UserController@index',
        'controller' => 'App\\Http\\Controllers\\UserController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::wRWG5LBuuJVk7m3w',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::65jEDzxSpXmPalec' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/users/counselors',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\UserController@counselors',
        'controller' => 'App\\Http\\Controllers\\UserController@counselors',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::65jEDzxSpXmPalec',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::XcUSYXSj1IumbDiJ' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/users/peer-counselors',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\UserController@peerCounselors',
        'controller' => 'App\\Http\\Controllers\\UserController@peerCounselors',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::XcUSYXSj1IumbDiJ',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::hDrGXpFMI4Th1gKm' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/users/students',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\UserController@students',
        'controller' => 'App\\Http\\Controllers\\UserController@students',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::hDrGXpFMI4Th1gKm',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::dC7LVzr3zmwW6gcX' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/users/counselors/{id}/approve',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'admin',
        ),
        'uses' => 'App\\Http\\Controllers\\UserController@approveCounselor',
        'controller' => 'App\\Http\\Controllers\\UserController@approveCounselor',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::dC7LVzr3zmwW6gcX',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::QACh9oH3gkUaeCqa' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/users/counselors/approve-bulk',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'admin',
        ),
        'uses' => 'App\\Http\\Controllers\\UserController@approveCounselorsBulk',
        'controller' => 'App\\Http\\Controllers\\UserController@approveCounselorsBulk',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::QACh9oH3gkUaeCqa',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::D0oCrSRb4aCLYbaF' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/users/counselors/{id}/reject',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'admin',
        ),
        'uses' => 'App\\Http\\Controllers\\UserController@rejectCounselor',
        'controller' => 'App\\Http\\Controllers\\UserController@rejectCounselor',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::D0oCrSRb4aCLYbaF',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::iVXy7cOuXF01llLv' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/users/counselors/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'admin',
        ),
        'uses' => 'App\\Http\\Controllers\\UserController@destroyCounselor',
        'controller' => 'App\\Http\\Controllers\\UserController@destroyCounselor',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::iVXy7cOuXF01llLv',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::gTDMobWQJJ299jvV' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/peer/dashboard',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'role:peer_counselor',
        ),
        'uses' => 'App\\Http\\Controllers\\PeerSupportController@dashboard',
        'controller' => 'App\\Http\\Controllers\\PeerSupportController@dashboard',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::gTDMobWQJJ299jvV',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::aLJXJqD1qQXR7Jjo' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/peer/escalations',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'role:peer_counselor',
        ),
        'uses' => 'App\\Http\\Controllers\\PeerSupportController@escalations',
        'controller' => 'App\\Http\\Controllers\\PeerSupportController@escalations',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::aLJXJqD1qQXR7Jjo',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::dpVU3dvdWWacHQm4' => 
    array (
      'methods' => 
      array (
        0 => 'PATCH',
      ),
      'uri' => 'api/peer/availability',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'role:peer_counselor',
        ),
        'uses' => 'App\\Http\\Controllers\\PeerSupportController@setAvailability',
        'controller' => 'App\\Http\\Controllers\\PeerSupportController@setAvailability',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::dpVU3dvdWWacHQm4',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::03ifOvUUueQU4XRM' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/institution-accounts',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'admin',
        ),
        'uses' => 'App\\Http\\Controllers\\InstitutionAccountController@index',
        'controller' => 'App\\Http\\Controllers\\InstitutionAccountController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::03ifOvUUueQU4XRM',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::B6fYPJ0C8g5KQ65C' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/institution-accounts',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'admin',
        ),
        'uses' => 'App\\Http\\Controllers\\InstitutionAccountController@store',
        'controller' => 'App\\Http\\Controllers\\InstitutionAccountController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::B6fYPJ0C8g5KQ65C',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::Q0dVWLjWGydp4CPJ' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
      ),
      'uri' => 'api/institution-accounts/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'admin',
        ),
        'uses' => 'App\\Http\\Controllers\\InstitutionAccountController@update',
        'controller' => 'App\\Http\\Controllers\\InstitutionAccountController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::Q0dVWLjWGydp4CPJ',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::DsZ8W9vBmBeYVBCi' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/institution-accounts/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'admin',
        ),
        'uses' => 'App\\Http\\Controllers\\InstitutionAccountController@destroy',
        'controller' => 'App\\Http\\Controllers\\InstitutionAccountController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::DsZ8W9vBmBeYVBCi',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::uVFzXuO7RAQHwfjk' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/profile',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\ProfileController@show',
        'controller' => 'App\\Http\\Controllers\\ProfileController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::uVFzXuO7RAQHwfjk',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::hwwJBTZNq5H8SK3k' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
      ),
      'uri' => 'api/profile',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\ProfileController@update',
        'controller' => 'App\\Http\\Controllers\\ProfileController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::hwwJBTZNq5H8SK3k',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::66BmX5oC7dvNpcoB' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/ai/wellness-chat/history',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:ai-read',
        ),
        'uses' => 'App\\Http\\Controllers\\AIWellnessChatController@history',
        'controller' => 'App\\Http\\Controllers\\AIWellnessChatController@history',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::66BmX5oC7dvNpcoB',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::3sqJJ4FwZLK0ZXiS' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/ai/wellness-chat',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:ai-chat',
        ),
        'uses' => 'App\\Http\\Controllers\\AIWellnessChatController@chat',
        'controller' => 'App\\Http\\Controllers\\AIWellnessChatController@chat',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::3sqJJ4FwZLK0ZXiS',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::M3kNKep2pdk8AARJ' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/ai-diagnostics',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\AIDiagnosticController@index',
        'controller' => 'App\\Http\\Controllers\\AIDiagnosticController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::M3kNKep2pdk8AARJ',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::nqWXEUEvnMACbPx2' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/ai-diagnostics/summary',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\AIDiagnosticController@summary',
        'controller' => 'App\\Http\\Controllers\\AIDiagnosticController@summary',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::nqWXEUEvnMACbPx2',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::09vFgejCKR4JtGvZ' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/ai-diagnostics/latest',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\AIDiagnosticController@latest',
        'controller' => 'App\\Http\\Controllers\\AIDiagnosticController@latest',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::09vFgejCKR4JtGvZ',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::Lcs7waKRCBVpsXGE' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/ai-diagnostics/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\AIDiagnosticController@show',
        'controller' => 'App\\Http\\Controllers\\AIDiagnosticController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::Lcs7waKRCBVpsXGE',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::PV6hHeENCPguuPb4' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/sessions/{id}/analyze',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\AIDiagnosticController@analyzeSession',
        'controller' => 'App\\Http\\Controllers\\AIDiagnosticController@analyzeSession',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::PV6hHeENCPguuPb4',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::hFZyOMmjXT1NJDfG' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/appointments/{id}/analyze',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\AIDiagnosticController@analyzeAppointment',
        'controller' => 'App\\Http\\Controllers\\AIDiagnosticController@analyzeAppointment',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::hFZyOMmjXT1NJDfG',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::anlV0CbaRgDLHYjq' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/diagnostics/questionnaire',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\DiagnosticController@getQuestionnaire',
        'controller' => 'App\\Http\\Controllers\\DiagnosticController@getQuestionnaire',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::anlV0CbaRgDLHYjq',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::F4mFmAZEpVx7I83V' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/diagnostics/analyze',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:diagnostics-submit',
        ),
        'uses' => 'App\\Http\\Controllers\\DiagnosticController@analyze',
        'controller' => 'App\\Http\\Controllers\\DiagnosticController@analyze',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::F4mFmAZEpVx7I83V',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::77V92WMlIfEjgQWS' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/diagnostics/history',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\DiagnosticController@getHistory',
        'controller' => 'App\\Http\\Controllers\\DiagnosticController@getHistory',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::77V92WMlIfEjgQWS',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::3iqHUdFuRcjbfiZc' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/diagnostics/latest',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\DiagnosticController@getLatest',
        'controller' => 'App\\Http\\Controllers\\DiagnosticController@getLatest',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::3iqHUdFuRcjbfiZc',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::9WWNW6O30mMZvdkz' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/diagnostics/trends',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\DiagnosticController@getTrends',
        'controller' => 'App\\Http\\Controllers\\DiagnosticController@getTrends',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::9WWNW6O30mMZvdkz',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::o7SuzIWEY3YN148j' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/diagnostics/counselor-dashboard',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'counselor',
        ),
        'uses' => 'App\\Http\\Controllers\\DiagnosticController@getCounselorDashboard',
        'controller' => 'App\\Http\\Controllers\\DiagnosticController@getCounselorDashboard',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::o7SuzIWEY3YN148j',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::08vZCIa1K1UCNA8b' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/student-wellness/summary',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\StudentWellnessController@summary',
        'controller' => 'App\\Http\\Controllers\\StudentWellnessController@summary',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::08vZCIa1K1UCNA8b',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::oLYN1FSOPiAUOPXz' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/ml/counselor-matches',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\MlInsightsController@counselorMatches',
        'controller' => 'App\\Http\\Controllers\\MlInsightsController@counselorMatches',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::oLYN1FSOPiAUOPXz',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::KKD3fKvDB8VAlcwz' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/ml/health',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\MlInsightsController@health',
        'controller' => 'App\\Http\\Controllers\\MlInsightsController@health',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::KKD3fKvDB8VAlcwz',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::Iu9EfZse3jdPWMZ7' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/student-mood/today',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\StudentMoodController@today',
        'controller' => 'App\\Http\\Controllers\\StudentMoodController@today',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::Iu9EfZse3jdPWMZ7',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::DKQA2aWLzmQLg6Eq' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/student-mood',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\StudentMoodController@store',
        'controller' => 'App\\Http\\Controllers\\StudentMoodController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::DKQA2aWLzmQLg6Eq',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::sWbsdJsE0CvkxCVj' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/counselor-wellness',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\CounselorWellnessController@index',
        'controller' => 'App\\Http\\Controllers\\CounselorWellnessController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::sWbsdJsE0CvkxCVj',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::y7XfUkUwBVtjyVR7' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/counselor-wellness/summary',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\CounselorWellnessController@summary',
        'controller' => 'App\\Http\\Controllers\\CounselorWellnessController@summary',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::y7XfUkUwBVtjyVR7',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::JLJQmaXRuaCvBPmS' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/counselor-wellness',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\CounselorWellnessController@store',
        'controller' => 'App\\Http\\Controllers\\CounselorWellnessController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::JLJQmaXRuaCvBPmS',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::YH56mCklO5RqAuTq' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/counselor-wellness/health-check',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\CounselorWellnessController@runHealthCheck',
        'controller' => 'App\\Http\\Controllers\\CounselorWellnessController@runHealthCheck',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::YH56mCklO5RqAuTq',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::ZOXBvEXg8QQPt8AR' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/panic-logs',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\PanicLogController@index',
        'controller' => 'App\\Http\\Controllers\\PanicLogController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::ZOXBvEXg8QQPt8AR',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::OMTdA98kcA8PH7qu' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/panic-logs',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\PanicLogController@store',
        'controller' => 'App\\Http\\Controllers\\PanicLogController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::OMTdA98kcA8PH7qu',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::Bb3NyWdEDbyUz4x4' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
      ),
      'uri' => 'api/panic-logs/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
        ),
        'uses' => 'App\\Http\\Controllers\\PanicLogController@update',
        'controller' => 'App\\Http\\Controllers\\PanicLogController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::Bb3NyWdEDbyUz4x4',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::OlTJEDUxtaWhWAJF' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/settings',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'admin',
        ),
        'uses' => 'App\\Http\\Controllers\\SystemSettingController@index',
        'controller' => 'App\\Http\\Controllers\\SystemSettingController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::OlTJEDUxtaWhWAJF',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::Ugk2alYxYSF9oMgs' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
      ),
      'uri' => 'api/settings',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'admin',
        ),
        'uses' => 'App\\Http\\Controllers\\SystemSettingController@update',
        'controller' => 'App\\Http\\Controllers\\SystemSettingController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::Ugk2alYxYSF9oMgs',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::TTHL7rqe49UzLtIi' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/settings/clear-cache',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'admin',
        ),
        'uses' => 'App\\Http\\Controllers\\SystemSettingController@clearCache',
        'controller' => 'App\\Http\\Controllers\\SystemSettingController@clearCache',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::TTHL7rqe49UzLtIi',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::hYnMpszPlF6kYl9N' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/tips',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'admin',
        ),
        'uses' => 'App\\Http\\Controllers\\TipController@index',
        'controller' => 'App\\Http\\Controllers\\TipController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::hYnMpszPlF6kYl9N',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::2JUYRKLLSpOJ489d' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/tips',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'admin',
        ),
        'uses' => 'App\\Http\\Controllers\\TipController@store',
        'controller' => 'App\\Http\\Controllers\\TipController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::2JUYRKLLSpOJ489d',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::zgNU2HAUEFA8x4hT' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
      ),
      'uri' => 'api/tips/{tip}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'admin',
        ),
        'uses' => 'App\\Http\\Controllers\\TipController@update',
        'controller' => 'App\\Http\\Controllers\\TipController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::zgNU2HAUEFA8x4hT',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::Nbwp0YaInVAiZo2w' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/tips/{tip}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'admin',
        ),
        'uses' => 'App\\Http\\Controllers\\TipController@destroy',
        'controller' => 'App\\Http\\Controllers\\TipController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::Nbwp0YaInVAiZo2w',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::FJzJpUXhrUWZOvli' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/admin/add-tip',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'admin',
        ),
        'uses' => 'App\\Http\\Controllers\\TipController@store',
        'controller' => 'App\\Http\\Controllers\\TipController@store',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::FJzJpUXhrUWZOvli',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::NnxVtEk8GGmGR8Ji' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
      ),
      'uri' => 'api/admin/update-tip/{tip}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'admin',
        ),
        'uses' => 'App\\Http\\Controllers\\TipController@update',
        'controller' => 'App\\Http\\Controllers\\TipController@update',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::NnxVtEk8GGmGR8Ji',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::mdMcFsp49P0UVbry' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/admin/delete-tip/{tip}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'admin',
        ),
        'uses' => 'App\\Http\\Controllers\\TipController@destroy',
        'controller' => 'App\\Http\\Controllers\\TipController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::mdMcFsp49P0UVbry',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::ZktimhuKTedNkepb' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/backups',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'admin',
        ),
        'uses' => 'App\\Http\\Controllers\\BackupController@index',
        'controller' => 'App\\Http\\Controllers\\BackupController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::ZktimhuKTedNkepb',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::a85mFMuRWG1vMiuU' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/backups/verify',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'admin',
        ),
        'uses' => 'App\\Http\\Controllers\\BackupController@verify',
        'controller' => 'App\\Http\\Controllers\\BackupController@verify',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::a85mFMuRWG1vMiuU',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::G5ZdbNi8LWYKmFkI' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/backups/drill',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'admin',
        ),
        'uses' => 'App\\Http\\Controllers\\BackupController@drill',
        'controller' => 'App\\Http\\Controllers\\BackupController@drill',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::G5ZdbNi8LWYKmFkI',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::NWzoMMHlDnwfNEo9' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/activity-logs',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'admin',
        ),
        'uses' => 'App\\Http\\Controllers\\ActivityLogController@index',
        'controller' => 'App\\Http\\Controllers\\ActivityLogController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::NWzoMMHlDnwfNEo9',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::dy72BC2zXqrGqeF0' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/activity-logs/stream',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'admin',
        ),
        'uses' => 'App\\Http\\Controllers\\ActivityLogController@stream',
        'controller' => 'App\\Http\\Controllers\\ActivityLogController@stream',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::dy72BC2zXqrGqeF0',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::4a3yFfHccGiyGQzt' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/activity-logs/stats',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'admin',
        ),
        'uses' => 'App\\Http\\Controllers\\ActivityLogController@stats',
        'controller' => 'App\\Http\\Controllers\\ActivityLogController@stats',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::4a3yFfHccGiyGQzt',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::w7nc6OX2iwhOtsYH' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/data-access-logs',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'admin',
        ),
        'uses' => 'App\\Http\\Controllers\\DataAccessLogController@index',
        'controller' => 'App\\Http\\Controllers\\DataAccessLogController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::w7nc6OX2iwhOtsYH',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::4nf8eqJVOGt7GKa8' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/ai-reports',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'admin',
        ),
        'uses' => 'App\\Http\\Controllers\\AIReportController@index',
        'controller' => 'App\\Http\\Controllers\\AIReportController@index',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::4nf8eqJVOGt7GKa8',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::bSQa8VrpcXR87A6u' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/ai-reports/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'admin',
        ),
        'uses' => 'App\\Http\\Controllers\\AIReportController@show',
        'controller' => 'App\\Http\\Controllers\\AIReportController@show',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::bSQa8VrpcXR87A6u',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::oJU2oAwXtvCfcVL5' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/ai-reports/generate',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'admin',
        ),
        'uses' => 'App\\Http\\Controllers\\AIReportController@generate',
        'controller' => 'App\\Http\\Controllers\\AIReportController@generate',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::oJU2oAwXtvCfcVL5',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::xq5UklS638m1LNDu' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/ai-reports/{id}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'admin',
        ),
        'uses' => 'App\\Http\\Controllers\\AIReportController@destroy',
        'controller' => 'App\\Http\\Controllers\\AIReportController@destroy',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::xq5UklS638m1LNDu',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::ropiCxJFrrNWMeSS' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/integrations/academic-risk/events',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'admin',
        ),
        'uses' => 'App\\Http\\Controllers\\AcademicRiskWebhookController@events',
        'controller' => 'App\\Http\\Controllers\\AcademicRiskWebhookController@events',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::ropiCxJFrrNWMeSS',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::GptAdXXaPZXtDUpQ' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/integrations/academic-risk/runs',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'admin',
        ),
        'uses' => 'App\\Http\\Controllers\\AcademicRiskWebhookController@runs',
        'controller' => 'App\\Http\\Controllers\\AcademicRiskWebhookController@runs',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::GptAdXXaPZXtDUpQ',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::6sWOi8HrmF08AS2v' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/openrouter/chat',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:ai-chat',
        ),
        'uses' => 'App\\Http\\Controllers\\OpenRouterChatController@sendMessage',
        'controller' => 'App\\Http\\Controllers\\OpenRouterChatController@sendMessage',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::6sWOi8HrmF08AS2v',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::nOSoyyKvd1OIH56u' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/openrouter/stream',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:ai-chat',
        ),
        'uses' => 'App\\Http\\Controllers\\OpenRouterChatController@streamMessage',
        'controller' => 'App\\Http\\Controllers\\OpenRouterChatController@streamMessage',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::nOSoyyKvd1OIH56u',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::86nd8vgA9dYYxXXj' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/openrouter/simple-chat',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:ai-chat',
        ),
        'uses' => 'App\\Http\\Controllers\\OpenRouterChatController@simpleChat',
        'controller' => 'App\\Http\\Controllers\\OpenRouterChatController@simpleChat',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::86nd8vgA9dYYxXXj',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::MIRpeXgpM0a7By2Z' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/openrouter/models',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:ai-read',
        ),
        'uses' => 'App\\Http\\Controllers\\OpenRouterChatController@getModels',
        'controller' => 'App\\Http\\Controllers\\OpenRouterChatController@getModels',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::MIRpeXgpM0a7By2Z',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::zGgAtBNIafcSJOGM' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/openrouter/conversations',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:ai-read',
        ),
        'uses' => 'App\\Http\\Controllers\\OpenRouterChatController@getConversations',
        'controller' => 'App\\Http\\Controllers\\OpenRouterChatController@getConversations',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::zGgAtBNIafcSJOGM',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::ffG6WisYjoi1webz' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/openrouter/conversations',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:ai-chat',
        ),
        'uses' => 'App\\Http\\Controllers\\OpenRouterChatController@createConversation',
        'controller' => 'App\\Http\\Controllers\\OpenRouterChatController@createConversation',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::ffG6WisYjoi1webz',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::rNTU9f5FJBDhtcnL' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/openrouter/conversations/{conversationId}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:ai-read',
        ),
        'uses' => 'App\\Http\\Controllers\\OpenRouterChatController@getConversationMessages',
        'controller' => 'App\\Http\\Controllers\\OpenRouterChatController@getConversationMessages',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::rNTU9f5FJBDhtcnL',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
        'conversationId' => '[0-9]+',
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::yfHgHjvEZwkjjHL6' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/openrouter/conversations/{conversationId}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'track.device_session',
          3 => 'session.timeout',
          4 => 'audit.admin',
          5 => 'audit.access',
          6 => 'counselor.2fa',
          7 => 'throttle:ai-chat',
        ),
        'uses' => 'App\\Http\\Controllers\\OpenRouterChatController@deleteConversation',
        'controller' => 'App\\Http\\Controllers\\OpenRouterChatController@deleteConversation',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'generated::yfHgHjvEZwkjjHL6',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
        'conversationId' => '[0-9]+',
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
  ),
)
);
