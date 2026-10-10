<?php
$input = "If you are assigning this to your designer/team, you can make the first task simply:\n> Task 01: Create Moodboard for LinkedIn & Instagram Social Media Design\n>\n> Objective: Develop a clear visual direction for Trivikra Tech communication.\n>\n> Research: Competitor pages, industry leaders.\n>\n> Moodboard should include: Color direction, typography, imagery.\n>\n> Deliverable: 1 finalized moodboard in Figma.";

function formatChatTest($text) {
    $text = htmlspecialchars($text, ENT_QUOTES, "UTF-8");
    
    // 1. Code blocks
    $codeBlocks = [];
    $text = preg_replace_callback('/```([a-zA-Z0-9_\-\+]*)\r?\n?([\s\S]*?)```/m', function($m) use (&$codeBlocks) {
        $placeholder = '###CHAT_CODE_BLOCK_' . count($codeBlocks) . '###';
        $lang = !empty($m[1]) ? htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') : 'CODE';
        $code = trim($m[2]);
        $codeHtml = '<pre class="chat-code-block"><div class="chat-code-header"><span>' . $lang . '</span></div><code>' . $code . '</code></pre>';
        $codeBlocks[$placeholder] = $codeHtml;
        return $placeholder;
    }, $text);
    
    // 2. Inline code
    $inlineCodes = [];
    $text = preg_replace_callback('/`([^`\r\n]+)`/', function($m) use (&$inlineCodes) {
        $placeholder = '###CHAT_INLINE_CODE_' . count($inlineCodes) . '###';
        $inlineCodes[$placeholder] = '<code class="chat-inline-code">' . $m[1] . '</code>';
        return $placeholder;
    }, $text);

    // 3. Markdown links & URLs
    $text = preg_replace('/\[([^\]\r\n]+)\]\((https?:\/\/[^\)\s]+)\)/', '<a href="$2" target="_blank" class="chat-link">$1</a>', $text);
    $text = preg_replace('/(?<!href=")(?<!href=\')(https?:\/\/[^\s<"\'\)]+)/i', '<a href="$1" target="_blank" class="chat-link">$1</a>', $text);

    // 4. Bold, Italic, Strikethrough
    $text = preg_replace('/\*\*([^\*\r\n]+)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/(?<=^|\s|[.,!?;:(\[])\*([^\*\r\n\s]+(?:\s+[^\*\r\n\s]+)*)\*(?=\s|$|[.,!?;:)\]])/', '<strong>$1</strong>', $text);
    $text = preg_replace('/(?<=^|\s|[.,!?;:(\[])_([^_\r\n\s]+(?:\s+[^_\r\n\s]+)*)_(?=\s|$|[.,!?;:)\]])/', '<em>$1</em>', $text);
    $text = preg_replace('/~~([^~\r\n]+)~~/', '<del>$1</del>', $text);
    $text = preg_replace('/(?<=^|\s|[.,!?;:(\[])~([^~\r\n\s]+(?:\s+[^~\r\n\s]+)*)~(?=\s|$|[.,!?;:)\]])/', '<del>$1</del>', $text);

    // 5. Contiguous Blockquotes and Lists Line-by-Line Grouping
    $lines = explode("\n", str_replace("\r\n", "\n", $text));
    $outLines = [];
    $inQuote = false;
    $quoteLines = [];
    $inList = false;
    $listType = 'ul';

    foreach ($lines as $line) {
        // Check if line is blockquote
        if (preg_match('/^(?:&gt;|>)[ ]?(.*)$/', $line, $qm)) {
            if ($inList) {
                $outLines[] = '</' . $listType . '>';
                $inList = false;
            }
            if (!$inQuote) {
                $inQuote = true;
                $quoteLines = [];
            }
            $quoteLines[] = $qm[1];
            continue;
        } else {
            if ($inQuote) {
                $qContent = trim(implode("<br>", $quoteLines));
                $qContent = preg_replace('/(?:<br\s*\/?>\s*){2,}/i', '<br><br>', $qContent);
                $qContent = preg_replace('/^(?:<br\s*\/?>\s*)+|(?:<br\s*\/?>\s*)+$/i', '', $qContent);
                if ($qContent !== '') {
                    $outLines[] = '<blockquote class="chat-blockquote">' . $qContent . '</blockquote>';
                }
                $inQuote = false;
                $quoteLines = [];
            }
        }

        // Check if line is list
        if (preg_match('/^[ ]*[-*•▪▫◆✓➢–—][ ]+(.+)$/u', $line, $lm)) {
            if (!$inList || $listType !== 'ul') {
                if ($inList) $outLines[] = '</' . $listType . '>';
                $outLines[] = '<ul class="chat-list">';
                $inList = true;
                $listType = 'ul';
            }
            $outLines[] = '<li>' . $lm[1] . '</li>';
        } elseif (preg_match('/^[ ]*(?:[0-9]+[.)]|\([0-9]+\)|[a-zA-Z][.)]|\([a-zA-Z]\))[ ]+(.+)$/u', $line, $lm)) {
            if (!$inList || $listType !== 'ol') {
                if ($inList) $outLines[] = '</' . $listType . '>';
                $outLines[] = '<ol class="chat-list">';
                $inList = true;
                $listType = 'ol';
            }
            $outLines[] = '<li>' . $lm[1] . '</li>';
        } else {
            if ($inList) {
                $outLines[] = '</' . $listType . '>';
                $inList = false;
            }
            $outLines[] = $line;
        }
    }

    if ($inQuote) {
        $qContent = trim(implode("<br>", $quoteLines));
        $qContent = preg_replace('/(?:<br\s*\/?>\s*){2,}/i', '<br><br>', $qContent);
        $qContent = preg_replace('/^(?:<br\s*\/?>\s*)+|(?:<br\s*\/?>\s*)+$/i', '', $qContent);
        if ($qContent !== '') {
            $outLines[] = '<blockquote class="chat-blockquote">' . $qContent . '</blockquote>';
        }
    }
    if ($inList) {
        $outLines[] = '</' . $listType . '>';
    }

    // 6. Preserve regular text newlines
    $finalText = '';
    foreach ($outLines as $idx => $line) {
        if (str_starts_with($line, '<ul') || str_starts_with($line, '<ol') || str_starts_with($line, '<li>') || str_starts_with($line, '</ul') || str_starts_with($line, '</ol') || str_starts_with($line, '<blockquote')) {
            $finalText .= $line;
        } else {
            $finalText .= ($idx > 0 && !empty($finalText) && !str_ends_with($finalText, '</ul>') && !str_ends_with($finalText, '</ol>') && !str_ends_with($finalText, '</blockquote>') ? '<br>' : '') . $line;
        }
    }
    $text = $finalText;

    foreach ($codeBlocks as $placeholder => $codeHtml) {
        $text = str_replace($placeholder, $codeHtml, $text);
    }
    foreach ($inlineCodes as $placeholder => $inlineHtml) {
        $text = str_replace($placeholder, $inlineHtml, $text);
    }

    return $text;
}

echo formatChatTest($input) . "\n";
