<?php
ob_start(); // Start output buffering to prevent premature output

session_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../vendor/autoload.php'; // Include Composer's autoloader

checkRole('teacher'); // Ensure only teachers can access this page

// Get attempt ID from URL
$attemptId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$attemptId) {
    die('Error: No attempt ID provided');
}

$conn = getDBConnection();

// Get attempt details
$stmt = $conn->prepare("
    SELECT 
        ea.*,
        e.id as exam_id,
        e.title as exam_title,
        e.description as exam_description,
        e.total_points,
        u.full_name as student_name,
        c.name as classroom_name
    FROM exam_attempts ea
    JOIN exams e ON ea.exam_id = e.id
    JOIN users u ON ea.student_id = u.id
    JOIN classroom_students cs ON u.id = cs.student_id
    JOIN classrooms c ON cs.classroom_id = c.id
    WHERE ea.id = ?
");
$stmt->execute([$attemptId]);
$attempt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attempt) {
    die('Error: Attempt not found');
}

// Get questions and answers
$questions = [];
$stmt = $conn->prepare("
    SELECT 
        q.*,
        q.question_type,
        q.order_num
    FROM questions q
    WHERE q.exam_id = ?
    ORDER BY q.order_num
");
$stmt->execute([$attempt['exam_id']]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all student answers
$stmt = $conn->prepare("
    SELECT 
        sa.*,
        q.points as max_points,
        mo.option_text as selected_option_text
    FROM student_answers sa
    JOIN questions q ON sa.question_id = q.id
    LEFT JOIN mcq_options mo ON sa.selected_option_id = mo.id
    WHERE sa.attempt_id = ?
");
$stmt->execute([$attemptId]);
$studentAnswers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Combine all answers into a single array indexed by question_id
$allAnswers = [];
foreach ($questions as $question) {
    $questionId = $question['id'];
    $allAnswers[$questionId] = [
        'question_text' => $question['question_text'],
        'question_type' => $question['question_type'],
        'max_points' => $question['points'],
        'answer' => null,
        'points_earned' => null,
        'teacher_comment' => null,
        'selected_option_text' => null,
        'is_correct' => null,
        'order_num' => $question['order_num']
    ];
}

// Process all answers
foreach ($studentAnswers as $answer) {
    $questionId = $answer['question_id'];
    if (isset($allAnswers[$questionId])) {
        $allAnswers[$questionId]['answer'] = $answer['answer_type'] === 'mcq' ? 
                                           $answer['selected_option_id'] : 
                                           $answer['answer_text'];
        $allAnswers[$questionId]['points_earned'] = $answer['points_earned'];
        $allAnswers[$questionId]['teacher_comment'] = $answer['teacher_comment'];
        $allAnswers[$questionId]['selected_option_text'] = $answer['selected_option_text'];
        $allAnswers[$questionId]['is_correct'] = $answer['is_correct'];
    }
}

// Sort answers by order_num
usort($allAnswers, function($a, $b) {
    return $a['order_num'] <=> $b['order_num'];
});

// Create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Quiztify');
$pdf->SetAuthor('Teacher');
$pdf->SetTitle('Exam Correction: ' . $attempt['exam_title']);
$pdf->SetSubject('Exam Correction for ' . $attempt['student_name']);

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// Set margins
$pdf->SetMargins(15, 15, 15);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 15);

// Set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Set font
$pdf->SetFont('helvetica', '', 10);

// Add a page
$pdf->AddPage();

// Set school/system logo if available
// $pdf->Image('path/to/logo.png', 15, 10, 30, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);

// Write the title
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'Exam Correction', 0, 1, 'C');
$pdf->Ln(2);

// Write exam and student information
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, htmlspecialchars($attempt['exam_title']), 0, 1, 'C');
$pdf->SetFont('helvetica', '', 10);
$pdf->Ln(5);

$html = '<table border="0" cellspacing="3" cellpadding="4">
    <tr>
        <td width="25%"><strong>Student:</strong></td>
        <td width="75%">'.htmlspecialchars($attempt['student_name']).'</td>
    </tr>
    <tr>
        <td><strong>Class:</strong></td>
        <td>'.htmlspecialchars($attempt['classroom_name']).'</td>
    </tr>
    <tr>
        <td><strong>Submission Date:</strong></td>
        <td>'.($attempt['end_time'] ? date('M j, Y g:i A', strtotime($attempt['end_time'])) : 'Not submitted').'</td>
    </tr>
    <tr>
        <td><strong>Final Score:</strong></td>
        <td>'.number_format($attempt['score'], 1).' / '.$attempt['total_points'].' ('.number_format(($attempt['score'] / $attempt['total_points']) * 100, 1).'%)</td>
    </tr>
</table>';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Ln(5);

// Write overall feedback if available
if (!empty($attempt['teacher_feedback'])) {
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 10, 'Overall Feedback:', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->MultiCell(0, 10, htmlspecialchars($attempt['teacher_feedback']), 0, 'L');
    $pdf->Ln(5);
}

// Write questions and answers
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'Questions and Answers', 0, 1, 'L');
$pdf->SetDrawColor(200, 200, 200);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(5);

$questionNumber = 1;
foreach ($allAnswers as $questionId => $answer) {
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 10, 'Question ' . $questionNumber . ' (' . $answer['max_points'] . ' points)', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    
    // Question text
    $pdf->MultiCell(0, 10, htmlspecialchars($answer['question_text']), 0, 'L');
    $pdf->Ln(2);
    
    // Student's answer
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 10, 'Student Answer:', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    
    if ($answer['question_type'] === 'mcq') {
        $pdf->MultiCell(0, 10, htmlspecialchars($answer['selected_option_text'] ?? 'Option not found') . 
                       ($answer['is_correct'] ? ' ✓' : ' ✗'), 0, 'L');
    } elseif ($answer['question_type'] === 'true_false') {
        $pdf->MultiCell(0, 10, htmlspecialchars($answer['answer'] ?? 'No answer') . 
                       ($answer['is_correct'] ? ' ✓' : ' ✗'), 0, 'L');
    } else {
        $pdf->MultiCell(0, 10, htmlspecialchars($answer['answer'] ?? 'No answer provided'), 0, 'L');
    }
    $pdf->Ln(2);
    
    // Points earned
    $pdf->SetFillColor(245, 245, 245);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(30, 10, 'Points Earned:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 10, ($answer['points_earned'] ?? '0') . ' / ' . $answer['max_points'], 0, 1, 'L');
    
    // Teacher comment if available
    if (!empty($answer['teacher_comment'])) {
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(30, 10, 'Feedback:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->MultiCell(0, 10, htmlspecialchars($answer['teacher_comment']), 0, 'L');
    }
    
    $pdf->Ln(5);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(5);
    
    $questionNumber++;
}

// Footer with timestamp
$pdf->SetY(-15);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(0, 10, 'Generated on ' . date('Y-m-d H:i:s') . ' | Page ' . $pdf->getAliasNumPage() . '/' . $pdf->getAliasNbPages(), 0, 0, 'C');

// Clean the output buffer before sending PDF
ob_end_clean();

// Output the PDF
$pdfName = 'exam_correction_' . $attempt['student_name'] . '_' . date('Ymd') . '.pdf';
$pdf->Output($pdfName, 'D'); // 'D' means download