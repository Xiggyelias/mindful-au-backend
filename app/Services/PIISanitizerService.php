<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PIISanitizerService
{
    public const TOKEN_STUDENT = '[Student]';

    public const TOKEN_COUNSELOR = '[Counselor]';

    public const TOKEN_USER = '[User]';

    public const TOKEN_EMAIL = '[Email]';

    public const TOKEN_PHONE = '[Phone]';

    public const TOKEN_STUDENT_ID = '[Student_ID]';

    public const TOKEN_COUNSELOR_ID = '[Counselor_ID]';

    public const TOKEN_LOCATION = '[Campus_Location]';

    public const TOKEN_ROOM = '[Room_Number]';

    public const TOKEN_CARD = '[Payment_Info]';

    public const TOKEN_NATIONAL_ID = '[National_ID]';

    public const TOKEN_IP = '[IP_Address]';

    /**
     * Common emotional and mental state words that frequently follow "I am" / "I'm" / "Feeling"
     * which MUST NOT be masked as personal names.
     */
    private const CLINICAL_STATE_WORDS = [
        'depressed', 'anxious', 'suicidal', 'sad', 'stressed', 'overwhelmed',
        'exhausted', 'tired', 'hopeless', 'helpless', 'alone', 'scared',
        'terrified', 'nervous', 'panicking', 'panic', 'angry', 'frustrated',
        'hurting', 'broken', 'fine', 'ok', 'okay', 'good', 'better', 'worse',
        'struggling', 'numb', 'lost', 'confused', 'guilty', 'ashamed', 'worried',
        'drained', 'burnout', 'burned', 'burnt', 'failing', 'safe', 'ready',
        'unwell', 'sick', 'ill', 'grieving', 'mourning', 'empty', 'lonely',
        'restless', 'sleepless', 'unable', 'trying', 'here', 'back', 'done',
        'crying', 'shaking', 'so', 'very', 'really', 'extremely', 'feeling',
        'not', 'just', 'still', 'always', 'currently', 'having', 'going',
        'unhappy', 'worthless', 'desperate', 'afraid', 'panicky', 'isolated',
        'detached', 'uneasy', 'miserable', 'down', 'upset', 'shaken', 'insecure',
    ];

    /**
     * Sanitize a single text string, removing personal identifying information (PII)
     * while preserving clinical distress signals, emotional cues, and context.
     *
     * @param  mixed  $knownUsers  User, array of Users, Collection of Users, or null
     */
    public function sanitizeText(string $text, mixed $knownUsers = null): string
    {
        if (trim($text) === '') {
            return $text;
        }

        // 1. Mask known user data if available (exact matches for name, email, student ID)
        if ($knownUsers !== null) {
            $text = $this->maskKnownUserData($text, $knownUsers);
        }

        // 2. Mask pattern-based email addresses
        $text = $this->maskEmails($text);

        // 3. Mask pattern-based phone numbers (7 to 15 digits)
        $text = $this->maskPhoneNumbers($text);

        // 4. Mask pattern-based student, matriculation, and national IDs
        $text = $this->maskIdentificationNumbers($text);

        // 5. Mask campus hostels, hall of residence, and room numbers
        $text = $this->maskCampusLocations($text);

        // 6. Mask payment card numbers
        $text = $this->maskFinancialInfo($text);

        // 7. Mask IP addresses
        $text = $this->maskIpAddresses($text);

        // 8. Mask honorific names (Dr. Smith, Professor Jones, etc.)
        $text = $this->maskHonorificNames($text);

        // 9. Mask self-introduction names ("My name is John", "I am Alice") while protecting emotional states
        $text = $this->maskSelfIntroductions($text);

        return $text;
    }

    /**
     * Sanitize an array of messages before sending them to an external LLM provider.
     */
    public function sanitizeMessages(array $messages, mixed $knownUsers = null): array
    {
        $sanitized = [];

        foreach ($messages as $message) {
            if (! is_array($message)) {
                $sanitized[] = $message;

                continue;
            }

            $sanitizedMessage = $message;

            if (isset($sanitizedMessage['content']) && is_string($sanitizedMessage['content'])) {
                $sanitizedMessage['content'] = $this->sanitizeText($sanitizedMessage['content'], $knownUsers);
            }

            $sanitized[] = $sanitizedMessage;
        }

        return $sanitized;
    }

    /**
     * Append a strict privacy directive to a system prompt to prevent the model from requesting PII.
     */
    public function appendPrivacySystemDirective(string $systemPrompt): string
    {
        $directive = "\n\nCRITICAL PRIVACY DIRECTIVE:\n"
            ."- All Personally Identifiable Information (PII) including real names, phone numbers, emails, student IDs, and residential addresses has been masked for user protection.\n"
            ."- NEVER ask the student or user for their real name, phone number, physical address, student ID, or personal email.\n"
            ."- Address the user warmly as a student or peer without using or guessing any personal names.\n"
            .'- Focus 100% on emotional support, evidence-based coping strategies, and connecting to on-duty counselors.';

        return $systemPrompt.$directive;
    }

    /**
     * Mask known user profile fields (names, emails, IDs) with appropriate role tokens.
     */
    private function maskKnownUserData(string $text, mixed $knownUsers): string
    {
        $users = $this->normalizeUsers($knownUsers);

        foreach ($users as $user) {
            $isCounselor = false;
            if ($user->relationLoaded('roles')) {
                $isCounselor = $user->roles->contains(function ($r) {
                    $roleName = is_object($r) ? ($r->role ?? '') : (is_array($r) ? ($r['role'] ?? '') : '');

                    return $roleName === 'counselor';
                });
            } elseif ($user->exists && method_exists($user, 'hasRole')) {
                try {
                    $isCounselor = $user->hasRole('counselor');
                } catch (\Throwable) {
                    $isCounselor = false;
                }
            }

            $nameToken = $isCounselor ? self::TOKEN_COUNSELOR : self::TOKEN_STUDENT;
            $idToken = $isCounselor ? self::TOKEN_COUNSELOR_ID : self::TOKEN_STUDENT_ID;

            // 1. Email masking
            if (! empty($user->email)) {
                $text = str_ireplace($user->email, self::TOKEN_EMAIL, $text);
            }

            // 2. Profile details masking
            if ($user->relationLoaded('profile') || method_exists($user, 'profile')) {
                $profile = $user->profile;
                if ($profile) {
                    // ID Number
                    if (! empty($profile->id_number)) {
                        $text = str_ireplace($profile->id_number, $idToken, $text);
                    }

                    // Full Name & individual words
                    if (! empty($profile->full_name)) {
                        $fullName = trim($profile->full_name);
                        $text = str_ireplace($fullName, $nameToken, $text);

                        // Also mask individual tokens of the name if >= 3 characters and not common stop words
                        $parts = preg_split('/\s+/', $fullName);
                        foreach ($parts as $part) {
                            $part = trim($part);
                            if (mb_strlen($part) >= 3 && ! $this->isCommonClinicalOrEnglishWord($part)) {
                                $pattern = '/\b'.preg_quote($part, '/').'\b/i';
                                $text = (string) preg_replace($pattern, $nameToken, $text);
                            }
                        }
                    }
                }
            }
        }

        return $text;
    }

    /**
     * Mask email addresses matching standard RFC/domain patterns.
     */
    private function maskEmails(string $text): string
    {
        return (string) preg_replace(
            '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/',
            self::TOKEN_EMAIL,
            $text
        );
    }

    /**
     * Mask telephone and mobile numbers while avoiding years (e.g. 2024, 2026) and scale ratings.
     */
    private function maskPhoneNumbers(string $text): string
    {
        // Matches international formats (+263 77 123 4567, 0771234567, +1-555-123-4567)
        return (string) preg_replace_callback(
            '/(?:\+?\d{1,4}[-.\s]?)?(?:\(?\d{2,4}\)?[-.\s]?)?\d{3,4}[-.\s]?\d{3,4}\b/',
            function ($matches) {
                $match = $matches[0];
                // Count digits in the match
                $digits = preg_replace('/\D/', '', $match);
                $digitCount = strlen($digits);

                // Phone numbers typically have between 7 and 15 digits
                if ($digitCount >= 7 && $digitCount <= 15) {
                    return self::TOKEN_PHONE;
                }

                return $match;
            },
            $text
        );
    }

    /**
     * Mask university matriculation IDs, staff IDs, and national identity numbers.
     */
    private function maskIdentificationNumbers(string $text): string
    {
        // Format: AU/2023/1234, CS/2022/045, etc.
        $text = (string) preg_replace(
            '/\b[A-Za-z]{2,4}\/\d{4,8}(?:\/\d{1,4})?\b/',
            self::TOKEN_STUDENT_ID,
            $text
        );

        // Format: STU-123456, MAT123456, REG-998877, STAFF_8833
        $text = (string) preg_replace(
            '/\b(?:STU|MAT|REG|STAFF|EMP)[-_#]?\d{4,10}\b/i',
            self::TOKEN_STUDENT_ID,
            $text
        );

        // Prefixed IDs: e.g. "matric: 12345678", "student id: 987654"
        $text = (string) preg_replace_callback(
            '/\b(matric(?:ulation)?|student\s*id|reg(?:istration)?\s*no|id\s*(?:no|number)|staff\s*id)[\s:#=-]{1,5}([A-Za-z0-9\/-]{4,20})\b/i',
            function ($matches) {
                return $matches[1].': '.self::TOKEN_STUDENT_ID;
            },
            $text
        );

        // Zimbabwe National ID format: e.g. 63-1234567-X89 or 29-123456-A12
        $text = (string) preg_replace(
            '/\b\d{2,3}-\d{5,7}-[A-Za-z]\d{2}\b/',
            self::TOKEN_NATIONAL_ID,
            $text
        );

        // US / International SSN / National ID: 123-45-6789
        $text = (string) preg_replace(
            '/\b\d{3}-\d{2}-\d{4}\b/',
            self::TOKEN_NATIONAL_ID,
            $text
        );

        return $text;
    }

    /**
     * Mask campus halls, dorms, hostels, and room numbers.
     */
    private function maskCampusLocations(string $text): string
    {
        // e.g. "Hall 3 Room 204", "New Hostel Block B", "Hostel 2", "Dorm A"
        $text = (string) preg_replace(
            '/\b(?:hall|hostel|residence|dorm|dormitory|block)[\s#]+[A-Za-z0-9\-]+(?:\s+(?:room|rm)[\s#]+[A-Za-z0-9\-]+)?\b/i',
            self::TOKEN_LOCATION,
            $text
        );

        // e.g. "Room 204", "Rm #102"
        $text = (string) preg_replace(
            '/\b(?:room|rm)[\s#]+[A-Za-z0-9\-]{2,6}\b/i',
            self::TOKEN_ROOM,
            $text
        );

        return $text;
    }

    /**
     * Mask credit/debit card numbers.
     */
    private function maskFinancialInfo(string $text): string
    {
        return (string) preg_replace(
            '/\b(?:\d{4}[-\s]?){3}\d{4}\b/',
            self::TOKEN_CARD,
            $text
        );
    }

    /**
     * Mask IPv4 addresses.
     */
    private function maskIpAddresses(string $text): string
    {
        return (string) preg_replace(
            '/\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\b/',
            self::TOKEN_IP,
            $text
        );
    }

    /**
     * Mask names following formal honorifics (e.g. Dr. Ndlovu, Prof. Smith, Mrs. Jones).
     */
    private function maskHonorificNames(string $text): string
    {
        return (string) preg_replace(
            '/\b(?:Dr\.|Doctor|Prof\.|Professor|Mr\.|Mrs\.|Ms\.)\s+[A-Z][a-z]+(?:\s+[A-Z][a-z]+)?\b/u',
            self::TOKEN_COUNSELOR,
            $text
        );
    }

    /**
     * Mask self-introductions ("my name is ...", "call me ...")
     * while strictly protecting emotional expressions like "I am feeling depressed", "I'm anxious", etc.
     */
    private function maskSelfIntroductions(string $text): string
    {
        return (string) preg_replace_callback(
            '/\b(my name is|i am|i\'m|call me|this is)\s+([A-Za-z]+(?:\s+[A-Za-z]+)?)\b/iu',
            function ($matches) {
                $prefix = $matches[1];
                $candidateName = trim($matches[2]);
                $words = preg_split('/\s+/', Str::lower($candidateName));

                // If any word in candidate name is a clinical or common descriptive state, do NOT mask
                foreach ($words as $word) {
                    if (in_array($word, self::CLINICAL_STATE_WORDS, true)) {
                        return $matches[0];
                    }
                }

                // If the phrase is just 1 or 2 proper-style names
                return $prefix.' '.self::TOKEN_STUDENT;
            },
            $text
        );
    }

    /**
     * Check if a single token is a clinical term or common word that should not be masked.
     */
    private function isCommonClinicalOrEnglishWord(string $word): bool
    {
        $lower = Str::lower(trim($word));

        return in_array($lower, self::CLINICAL_STATE_WORDS, true) ||
            in_array($lower, ['the', 'and', 'for', 'with', 'from', 'user', 'student', 'counselor'], true);
    }

    /**
     * Normalize mixed user input into an array of User objects.
     */
    private function normalizeUsers(mixed $input): array
    {
        if ($input instanceof User) {
            return [$input];
        }

        if ($input instanceof Collection) {
            return $input->filter(fn ($u) => $u instanceof User)->values()->all();
        }

        if (is_array($input)) {
            return array_values(array_filter($input, fn ($u) => $u instanceof User));
        }

        return [];
    }
}
