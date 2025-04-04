// Script to be added in the exercises tool.
import "jsplumb"
import "jquery-ui-touch-punch"
import "signature_pad"
import "../../../public/main/inc/lib/javascript/epiclock/javascript/jquery.dateformat.min.js"
import "../../../public/main/inc/lib/javascript/epiclock/javascript/jquery.epiclock.js"
import "../../../public/main/inc/lib/javascript/epiclock/renderers/minute/epiclock.minute.js"
import "./annotation"
import "../../../public/main/inc/lib/javascript/hotspot/js/hotspot.js"
import "../../../public/main/inc/lib/javascript/d3/jquery.xcolor.js"

document.addEventListener("DOMContentLoaded", function () {
  // Mapping French paths to their English equivalents
  var routeMapping = {
    "enregistrement-audio": "audio-recording-help",
  }

  var currentUrlParams = new URLSearchParams(window.location.search)
  var cid = currentUrlParams.get("cid") || "0"
  var sid = currentUrlParams.get("sid") || "0"
  var gid = currentUrlParams.get("gid") || "0"

  var links = document.querySelectorAll('a[href*="web"]')
  links.forEach(function (link) {
    var href = link.getAttribute("href")
    var pathSegments = href.split("/")
    var lastSegmentIndex = pathSegments.length - (pathSegments[pathSegments.length - 1] === "" ? 2 : 1)
    var lastPathSegment = pathSegments[lastSegmentIndex]

    if (lastPathSegment && routeMapping[lastPathSegment]) {
      var englishEquivalent = routeMapping[lastPathSegment]
      var newHref = `/main/inc/ajax/exercise.ajax.php?a=${englishEquivalent}&cid=${cid}&sid=${sid}&gid=${gid}`
      link.setAttribute("href", newHref)
      link.setAttribute("data-title", link.textContent.trim())
      link.classList.add("ajax")
    }
  })
})

window.SendEx = function (num) {
  const params = new URLSearchParams(window.location.search)
  const exerciseId = params.get("exerciseId") || 0
  const learnpathId = params.get("learnpath_id") || 0
  const learnpathItemId = params.get("learnpath_item_id") || 0
  const exeId = window?.exeId || ""

  let url = ""

  if (parseInt(num) === -1) {
    url = `exercise_result.php?${params.toString()}&exe_id=${exeId}&take_session=1&exerciseId=${exerciseId}&num=${num}&learnpath_item_id=${learnpathItemId}&learnpath_id=${learnpathId}`
  } else {
    url = `exercise_submit.php?${params.toString()}&tryagain=1&exerciseId=${exerciseId}&num=${num}&learnpath_item_id=${learnpathItemId}&learnpath_id=${learnpathId}`
  }

  window.location.href = url
}
